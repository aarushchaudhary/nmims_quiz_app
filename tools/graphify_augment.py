"""Augment graphify's AST graph for nmims_quiz_app with the structural edges
its PHP extractor cannot see in procedural scripts:
  - file -> file   include/require (imports), API calls (calls_api), page links (navigates_to)
  - file -> table  SQL reads/writes (reads_table / writes_table)
  - table -> table foreign keys (references), schema.sql contains tables
  - file -> role   role gates (requires_role)
  - file -> helper top-level calls to config/*.php helpers (calls)
Usage (from the repo root, after `graphify update .`):
    python3 tools/graphify_augment.py . && graphify cluster-only . --no-label
"""
import json, os, re, subprocess, sys
from collections import defaultdict

root = sys.argv[1]
gpath = os.path.join(root, "graphify-out", "graph.json")
g = json.load(open(gpath))
nodes = {n["id"]: n for n in g["nodes"]}
existing = {(e["source"], e["target"], e["relation"]) for e in g["links"]}
added_nodes, added_edges = 0, 0

files = subprocess.run(["git", "-C", root, "ls-files"], capture_output=True, text=True).stdout.split()
src = [f for f in files if f.endswith((".php", ".js")) and not f.startswith("lib/")]
file_id = {}
for n in g["nodes"]:
    sf = n.get("source_file")
    if sf and n["id"] == re.sub(r"[^a-z0-9]+", "_", os.path.splitext(sf.lower())[0]).strip("_"):
        file_id[sf] = n["id"]


def add_node(nid, label, ftype, source_file, loc="L1", **extra):
    global added_nodes
    if nid not in nodes:
        nodes[nid] = dict(id=nid, label=label, _origin="augment", file_type=ftype,
                          norm_label=label.lower(), source_file=source_file,
                          source_location=loc, **extra)
        added_nodes += 1


def add_edge(s, t, rel, sf, loc, conf="EXTRACTED", score=1.0):
    global added_edges
    if s == t or s not in nodes or t not in nodes or (s, t, rel) in existing:
        return
    existing.add((s, t, rel))
    g["links"].append(dict(source=s, target=t, relation=rel, _origin="augment",
                           confidence=conf, confidence_score=score, context="static",
                           source_file=sf, source_location=loc, weight=1.0))
    added_edges += 1


def line_of(text, idx):
    return "L%d" % (text.count("\n", 0, idx) + 1)


# ---- schema: tables + foreign keys ----
schema = open(os.path.join(root, "schema.sql")).read()
add_node("schema", "schema.sql", "code", "schema.sql")
tables = []
for m in re.finditer(r"CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?\s*\((.*?)\)\s*(?:ENGINE|;)", schema, re.S | re.I):
    t = m.group(1)
    tables.append(t)
    tid = "db_" + t
    add_node(tid, t + " (table)", "concept", "schema.sql", line_of(schema, m.start()))
    add_edge("schema", tid, "contains", "schema.sql", line_of(schema, m.start()))
for m in re.finditer(r"CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?\s*\((.*?)\)\s*(?:ENGINE|;)", schema, re.S | re.I):
    for fk in re.finditer(r"REFERENCES\s+`?(\w+)`?", m.group(2), re.I):
        add_edge("db_" + m.group(1), "db_" + fk.group(1), "references", "schema.sql",
                 line_of(schema, m.start(2) + fk.start()))
table_re = "|".join(sorted(tables, key=len, reverse=True))

# ---- roles ----
roles = {1: "Admin", 2: "Faculty", 3: "Placecom Officer", 4: "Student", 5: "School Head"}
for rid, name in roles.items():
    add_node("role_%d" % rid, "Role: " + name, "concept", "index.php")

# ---- helpers defined in config/*.php ----
helpers = {}
for n in nodes.values():
    if n.get("source_file", "").startswith("config/") and n["label"].endswith("()"):
        helpers[n["label"][:-2]] = n["id"]

for f in src:
    fid = file_id.get(f)
    if not fid:
        continue
    text = open(os.path.join(root, f), encoding="utf-8", errors="replace").read()
    fdir = os.path.dirname(f)

    # file -> file references
    for t in src + ["schema.sql"]:
        if t == f or t not in file_id:
            continue
        pats = [re.escape(t)]
        if os.path.dirname(t) == fdir:  # bare relative name like "view_quiz.php"
            pats.append(r"(?<![\w/])" + re.escape(os.path.basename(t)))
        m = re.search("|".join(pats), text)
        if not m:
            continue
        ln_start = text.rfind("\n", 0, m.start()) + 1
        line = text[ln_start:text.find("\n", m.start())]
        if re.search(r"\b(require|include)(_once)?\b", line):
            rel = "imports"
        elif t.endswith(".js"):
            rel = "loads_script"
        elif t.startswith("api/"):
            rel = "calls_api"
        else:
            rel = "navigates_to"
        add_edge(fid, file_id[t], rel, f, line_of(text, m.start()))

    # file -> table
    if f.endswith(".php"):
        for m in re.finditer(r"\b(DELETE\s+FROM|INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|UPDATE|FROM|JOIN)\s+`?(%s)`?\b" % table_re, text, re.I):
            kw = m.group(1).upper()
            rel = "reads_table" if kw in ("FROM", "JOIN") else "writes_table"
            add_edge(fid, "db_" + m.group(2), rel, f, line_of(text, m.start()))

    # file -> role gates
    for m in re.finditer(r"role_id'\]\s*(?:\?\?\s*\w+\s*)?(?:!==?|===?)\s*(\d)", text):
        add_edge(fid, "role_" + m.group(1), "requires_role", f, line_of(text, m.start()))
    for m in re.finditer(r"(?:require_role|in_array)\s*\([^\[]*\[([\d,\s]+)\]", text):
        for rid in re.findall(r"\d", m.group(1)):
            add_edge(fid, "role_" + rid, "requires_role", f, line_of(text, m.start()))
    if f == "index.php":
        for m in re.finditer(r"case (\d):[^\n]*\n\s*redirect\('([^']+)'\)", text):
            add_edge("role_" + m.group(1), file_id.get(m.group(2), ""), "lands_on", f, line_of(text, m.start()))

    # file -> helper calls (top-level procedural code)
    if not f.startswith("config/"):
        for name, hid in helpers.items():
            m = re.search(r"(?<![\w>$])%s\s*\(" % re.escape(name), text)
            if m:
                add_edge(fid, hid, "calls", f, line_of(text, m.start()), "EXTRACTED", 1.0)

g["nodes"] = list(nodes.values())
json.dump(g, open(gpath, "w"), indent=1)
counts = defaultdict(int)
for e in g["links"]:
    if e.get("_origin") == "augment":
        counts[e["relation"]] += 1
print("added nodes:", added_nodes, "added edges:", added_edges, dict(counts))
print("total:", len(g["nodes"]), "nodes,", len(g["links"]), "edges")
