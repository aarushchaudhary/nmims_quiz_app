# Graph Report - nmims_quiz_app  (2026-11-09)

## Corpus Check
- 134 files · ~228,399 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 4 file(s) not represented in the graph (top: (none) 3, .css 1)

## Summary
- 664 nodes · 2372 edges · 24 communities (14 shown, 10 thin omitted)
- Extraction: 87% EXTRACTED · 13% INFERRED · 0% AMBIGUOUS · INFERRED: 299 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `754f81e0`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- SharedTest.php
- StudentTest
- Frontend JS: Exam Engine & Proctoring
- PHP Tests: Security / Authz
- FacultyTest.php
- Load Test: Admin Behaviour
- Load Test: Faculty Behaviour
- Load Test: Head, Placecom & Guest
- Load Test: User Classes & Hooks
- Quiz & Exam Core (Faculty/Student APIs, Authz, Quiz Tables)
- PHP Tests: Admin & Test Harness
- Load Test: Student Exam Journey
- Load Test: Shared API
- Admin, Academic Hierarchy & Page Navigation
- PHP Tests: TestClient & Login
- PHP Tests: Runners
- Docs: README
- AdminTest.php
- PHP Tests: Shared & HTTP Client
- NMIMS Quiz App — Locust Load Tests
- question_template_b6f02dc0.md
- student_template_b765c57a.md
- Composer Dependencies
- Locust Setup Script

## God Nodes (most connected - your core abstractions)
1. `redirect()` - 67 edges
2. `_get()` - 67 edges
3. `quizzes (table)` - 54 edges
4. `get_base_url()` - 52 edges
5. `assert_eq()` - 51 edges
6. `Role: Admin` - 49 edges
7. `students (table)` - 44 edges
8. `questions (table)` - 34 edges
9. `assert_true()` - 32 edges
10. `Role: Faculty` - 31 edges

## Surprising Connections (you probably didn't know these)
- `Base URL (`config/base_url.php`)` --references--> `get_base_url()`  [INFERRED]
  README.md → config/base_url.php
- `How it works` --references--> `bootstrap_cleanup()`  [INFERRED]
  README.md → tests/bootstrap.php
- `How it works` --references--> `bootstrap_setup()`  [INFERRED]
  README.md → tests/bootstrap.php
- `How it works` --references--> `bootstrap_teardown()`  [INFERRED]
  README.md → tests/bootstrap.php
- ``StudentUser` (weight: 6 — highest load)` --references--> `StudentUser`  [INFERRED]
  locust/README.md → locust/locustfile.py

## Import Cycles
- None detected.

## Communities (24 total, 10 thin omitted)

### Community 0 - "SharedTest.php"
Cohesion: 0.12
Nodes (13): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#3}(), {closure#4}() (+5 more)

### Community 1 - "StudentTest"
Cohesion: 0.14
Nodes (9): {closure#1}(), {closure#10}(), {closure#2}(), {closure#3}(), {closure#6}(), {closure#7}(), {closure#8}(), {closure#9}() (+1 more)

### Community 2 - "Frontend JS: Exam Engine & Proctoring"
Cohesion: 0.10
Nodes (33): a(), addPenalty(), attachProctoringListeners(), b(), buildPalette(), c(), captureCurrentAnswer(), checkQuizStatus() (+25 more)

### Community 3 - "PHP Tests: Security / Authz"
Cohesion: 0.11
Nodes (19): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+11 more)

### Community 4 - "FacultyTest.php"
Cohesion: 0.14
Nodes (12): {closure#1}(), {closure#10}(), {closure#11}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+4 more)

### Community 7 - "Load Test: Head, Placecom & Guest"
Cohesion: 0.09
Nodes (5): HeadBehavior, _login(), _logout(), PlacecomBehavior, UnauthenticatedBrowser

### Community 8 - "Load Test: User Classes & Hooks"
Cohesion: 0.13
Nodes (14): AdminUser, FacultyUser, HeadUser, on_request(), on_test_start(), on_test_stop(), PlacecomUser, SharedApiUser (+6 more)

### Community 9 - "Quiz & Exam Core (Faculty/Student APIs, Authz, Quiz Tables)"
Cohesion: 0.10
Nodes (38): head_can_view_quiz(), json_fail(), require_eligible_quiz(), require_login(), require_own_attempt(), require_owned_attempt_as_faculty(), require_owned_question(), require_owned_quiz() (+30 more)

### Community 10 - "PHP Tests: Admin & Test Harness"
Cohesion: 0.31
Nodes (4): AdminTest, get_test_pdo(), assert_in(), assert_true()

### Community 13 - "Admin, Academic Hierarchy & Page Navigation"
Cohesion: 0.06
Nodes (42): get_asset_url(), get_base_url(), redirect(), courses (table), electives (table), question_difficulties (table), question_types (table), re_exam_group_students (table) (+34 more)

### Community 14 - "PHP Tests: TestClient & Login"
Cohesion: 0.14
Nodes (9): AuthTest, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#4}(), assert_eq() (+1 more)

### Community 16 - "Docs: README"
Cohesion: 0.05
Nodes (45): 🙏 Acknowledgments, 🏢 Admin, Admin Endpoints (role_id = 1), Admin Workflow, 🔌 API Reference, Authentication, 👨‍💻 Author, Base URL (`config/base_url.php`) (+37 more)

### Community 17 - "AdminTest.php"
Cohesion: 0.11
Nodes (15): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#2}() (+7 more)

### Community 19 - "NMIMS Quiz App — Locust Load Tests"
Cohesion: 0.20
Nodes (9): 1. Bootstrap the environment, 2. Activate the virtual environment, 3. Configure credentials, 4. Start Locust, Directory Structure, NMIMS Quiz App — Locust Load Tests, Performance Thresholds, Quick Start (+1 more)

## Knowledge Gaps
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `NMIMS Quiz App 📚` connect `Docs: README` to `Load Test: Admin Behaviour`?**
  _High betweenness centrality (0.394) - this node is a cross-community bridge._
- **Are the 7 inferred relationships involving `redirect()` (e.g. with `require_eligible_quiz()` and `require_login()`) actually correct?**
  _`redirect()` has 7 INFERRED edges - model-reasoned connections that need verification._
- **Should `SharedTest.php` be split into smaller, more focused modules?**
  _Cohesion score 0.11764705882352941 - nodes in this community are weakly interconnected._
- **Why does `🗄️ Database Schema` connect `Load Test: Admin Behaviour` to `Docs: README`?**
  _High betweenness centrality (0.316) - this node is a cross-community bridge._
- **Should `StudentTest` be split into smaller, more focused modules?**
  _Cohesion score 0.14285714285714285 - nodes in this community are weakly interconnected._
- **Why does `get_base_url()` connect `Admin, Academic Hierarchy & Page Navigation` to `Docs: README`, `Quiz & Exam Core (Faculty/Student APIs, Authz, Quiz Tables)`?**
  _High betweenness centrality (0.258) - this node is a cross-community bridge._
- **Should `Frontend JS: Exam Engine & Proctoring` be split into smaller, more focused modules?**
  _Cohesion score 0.09523809523809523 - nodes in this community are weakly interconnected._