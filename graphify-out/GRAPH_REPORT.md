# Graph Report - nmims_quiz_app  (2026-11-10)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 820 nodes · 2816 edges · 34 communities (24 shown, 10 thin omitted)
- Extraction: 86% EXTRACTED · 14% INFERRED · 0% AMBIGUOUS · INFERRED: 407 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `95c3d838`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- PHP Tests: Shared & HTTP Client
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
- k
- Docs: README
- 🌐 Deploying on Apache
- NMIMS Quiz App — Locust Load Tests
- question_template_b6f02dc0.md
- student_template_b765c57a.md
- 🔌 API reference
- 🌟 Features
- 📖 Using the app
- Composer Dependencies
- ⚙️ Configuration
- _post
- 🗄️ Database
- 🧪 Testing
- Locust Setup Script
- jquery-3.7.1.min.js
- select2-4.1.0-rc.0.min.js
- re

## God Nodes (most connected - your core abstractions)
1. `redirect()` - 67 edges
2. `_get()` - 67 edges
3. `assert_eq()` - 57 edges
4. `quizzes (table)` - 57 edges
5. `k()` - 52 edges
6. `get_base_url()` - 51 edges
7. `Role: Admin` - 49 edges
8. `students (table)` - 45 edges
9. `assert_true()` - 37 edges
10. `questions (table)` - 35 edges

## Surprising Connections (you probably didn't know these)
- ``AdminUser` (weight: 1)` --references--> `AdminUser`  [INFERRED]
  locust/README.md → locust/locustfile.py
- ``FacultyUser` (weight: 2)` --references--> `FacultyUser`  [INFERRED]
  locust/README.md → locust/locustfile.py
- ``PlacecomUser` (weight: 1)` --references--> `PlacecomUser`  [INFERRED]
  locust/README.md → locust/locustfile.py
- ``StudentUser` (weight: 6 — highest load)` --references--> `StudentUser`  [INFERRED]
  locust/README.md → locust/locustfile.py
- `require_eligible_quiz()` --calls--> `redirect()`  [INFERRED]
  config/authz.php → config/base_url.php

## Import Cycles
- None detected.

## Communities (34 total, 10 thin omitted)

### Community 0 - "PHP Tests: Shared & HTTP Client"
Cohesion: 0.12
Nodes (15): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#3}(), {closure#4}() (+7 more)

### Community 1 - "StudentTest"
Cohesion: 0.13
Nodes (12): {closure#1}(), {closure#10}(), {closure#11}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), {closure#6}() (+4 more)

### Community 2 - "Frontend JS: Exam Engine & Proctoring"
Cohesion: 0.14
Nodes (25): addPenalty(), attachProctoringListeners(), buildPalette(), captureCurrentAnswer(), checkQuizStatus(), clearResponse(), enterFullscreen(), finishExam() (+17 more)

### Community 3 - "PHP Tests: Security / Authz"
Cohesion: 0.10
Nodes (23): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+15 more)

### Community 4 - "FacultyTest.php"
Cohesion: 0.11
Nodes (16): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#2}() (+8 more)

### Community 7 - "Load Test: Head, Placecom & Guest"
Cohesion: 0.09
Nodes (5): HeadBehavior, _login(), _logout(), PlacecomBehavior, UnauthenticatedBrowser

### Community 8 - "Load Test: User Classes & Hooks"
Cohesion: 0.13
Nodes (14): AdminUser, FacultyUser, HeadUser, on_request(), on_test_start(), on_test_stop(), PlacecomUser, SharedApiUser (+6 more)

### Community 9 - "Quiz & Exam Core (Faculty/Student APIs, Authz, Quiz Tables)"
Cohesion: 0.08
Nodes (41): head_can_view_quiz(), json_fail(), require_eligible_quiz(), require_login(), require_own_attempt(), require_owned_attempt_as_faculty(), require_owned_question(), require_owned_quiz() (+33 more)

### Community 10 - "PHP Tests: Admin & Test Harness"
Cohesion: 0.21
Nodes (8): AdminTest, bootstrap_cleanup(), bootstrap_setup(), bootstrap_teardown(), get_test_pdo(), assert_in(), assert_true(), colorize()

### Community 13 - "Admin, Academic Hierarchy & Page Navigation"
Cohesion: 0.05
Nodes (57): get_asset_url(), get_base_url(), redirect(), admins (table), courses (table), electives (table), faculties (table), heads (table) (+49 more)

### Community 14 - "PHP Tests: TestClient & Login"
Cohesion: 0.08
Nodes (8): AuthTest, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), test(), TestClient

### Community 15 - "k"
Cohesion: 0.11
Nodes (44): d(), E(), f(), g(), k(), A(), B(), be() (+36 more)

### Community 16 - "Docs: README"
Cohesion: 0.12
Nodes (16): 🔑 Accounts and passwords, 🙏 Acknowledgments, 👨‍💻 Author, 🧠 Code knowledge graph, 📋 Contents, 📄 Excel import formats, 🧮 Grading rules, 🏗️ How it works (+8 more)

### Community 18 - "🌐 Deploying on Apache"
Cohesion: 0.29
Nodes (7): 1. Code, dependencies, database, 2. Apache + PHP-FPM, 3. Sizing for a full class, Alternative: mod_php, 🌐 Deploying on Apache, HTTPS, Serving from a sub-folder

### Community 19 - "NMIMS Quiz App — Locust Load Tests"
Cohesion: 0.18
Nodes (9): 1. Bootstrap the environment, 2. Activate the virtual environment, 3. Configure credentials, 4. Start Locust, Directory Structure, NMIMS Quiz App — Locust Load Tests, Performance Thresholds, Quick Start (+1 more)

### Community 22 - "🔌 API reference"
Cohesion: 0.33
Nodes (6): Admin (role 1), 🔌 API reference, Authentication, Faculty (role 2, own quizzes only), Shared, Student (role 4, own attempts only)

### Community 23 - "🌟 Features"
Cohesion: 0.40
Nodes (5): Admins, Faculty, 🌟 Features, Placement committee, school heads and other staff, Students

### Community 24 - "📖 Using the app"
Cohesion: 0.40
Nodes (5): Admins, Faculty: running a test, Placecom, school heads, directors, Students, 📖 Using the app

### Community 26 - "⚙️ Configuration"
Cohesion: 0.40
Nodes (5): ⚙️ Configuration, Environment variables, MariaDB settings, PHP settings (`php.ini`), Settings in the code

### Community 28 - "🗄️ Database"
Cohesion: 0.67
Nodes (3): Backups, 🗄️ Database, Fresh install

### Community 29 - "🧪 Testing"
Cohesion: 0.50
Nodes (4): Continuous integration, Load testing, Test suite, 🧪 Testing

### Community 31 - "jquery-3.7.1.min.js"
Cohesion: 0.09
Nodes (17): Ae(), B(), Be(), $e(), fe(), Ge(), Ie(), it() (+9 more)

### Community 32 - "select2-4.1.0-rc.0.min.js"
Cohesion: 0.15
Nodes (20): se(), T(), m(), ht(), L(), N(), o(), p() (+12 more)

### Community 33 - "re"
Cohesion: 0.27
Nodes (16): P(), ve(), c(), ee(), F(), I(), ne(), Q() (+8 more)

## Knowledge Gaps
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `k()` connect `k` to `select2-4.1.0-rc.0.min.js`, `re`?**
  _High betweenness centrality (0.051) - this node is a cross-community bridge._
- **Are the 7 inferred relationships involving `redirect()` (e.g. with `require_eligible_quiz()` and `require_login()`) actually correct?**
  _`redirect()` has 7 INFERRED edges - model-reasoned connections that need verification._
- **Should `PHP Tests: Shared & HTTP Client` be split into smaller, more focused modules?**
  _Cohesion score 0.12162162162162163 - nodes in this community are weakly interconnected._
- **Why does `redirect()` connect `Admin, Academic Hierarchy & Page Navigation` to `Quiz & Exam Core (Faculty/Student APIs, Authz, Quiz Tables)`, `PHP Tests: Security / Authz`?**
  _High betweenness centrality (0.049) - this node is a cross-community bridge._
- **Are the 56 inferred relationships involving `assert_eq()` (e.g. with `.testDashboardStats()` and `.testDashboardStatsGuest()`) actually correct?**
  _`assert_eq()` has 56 INFERRED edges - model-reasoned connections that need verification._
- **Should `StudentTest` be split into smaller, more focused modules?**
  _Cohesion score 0.13230769230769232 - nodes in this community are weakly interconnected._
- **Why does `quizzes (table)` connect `Quiz & Exam Core (Faculty/Student APIs, Authz, Quiz Tables)` to `StudentTest`, `PHP Tests: Security / Authz`, `FacultyTest.php`, `Admin, Academic Hierarchy & Page Navigation`?**
  _High betweenness centrality (0.045) - this node is a cross-community bridge._