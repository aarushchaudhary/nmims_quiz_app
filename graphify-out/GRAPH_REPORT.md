# Graph Report - nmims_quiz_app  (2026-11-09)

## Corpus Check
- cluster-only mode — file stats not available

## Summary
- 696 nodes · 2444 edges · 31 communities (21 shown, 10 thin omitted)
- Extraction: 87% EXTRACTED · 13% INFERRED · 0% AMBIGUOUS · INFERRED: 313 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `b745ea97`
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
- test.php
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

## God Nodes (most connected - your core abstractions)
1. `redirect()` - 67 edges
2. `_get()` - 67 edges
3. `assert_eq()` - 55 edges
4. `quizzes (table)` - 55 edges
5. `get_base_url()` - 51 edges
6. `Role: Admin` - 49 edges
7. `students (table)` - 44 edges
8. `assert_true()` - 35 edges
9. `questions (table)` - 34 edges
10. `student_attempts (table)` - 33 edges

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

## Communities (31 total, 10 thin omitted)

### Community 0 - "PHP Tests: Shared & HTTP Client"
Cohesion: 0.12
Nodes (14): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#2}(), {closure#3}(), {closure#4}() (+6 more)

### Community 1 - "StudentTest"
Cohesion: 0.23
Nodes (3): {closure#1}(), StudentTest, assert_key()

### Community 2 - "Frontend JS: Exam Engine & Proctoring"
Cohesion: 0.10
Nodes (33): a(), addPenalty(), attachProctoringListeners(), b(), buildPalette(), c(), captureCurrentAnswer(), checkQuizStatus() (+25 more)

### Community 3 - "PHP Tests: Security / Authz"
Cohesion: 0.10
Nodes (21): {closure#1}(), {closure#10}(), {closure#11}(), {closure#12}(), {closure#13}(), {closure#14}(), {closure#15}(), {closure#16}() (+13 more)

### Community 4 - "FacultyTest.php"
Cohesion: 0.29
Nodes (3): {closure#1}(), FacultyTest, assert_eq()

### Community 7 - "Load Test: Head, Placecom & Guest"
Cohesion: 0.09
Nodes (5): HeadBehavior, _login(), _logout(), PlacecomBehavior, UnauthenticatedBrowser

### Community 8 - "Load Test: User Classes & Hooks"
Cohesion: 0.13
Nodes (14): AdminUser, FacultyUser, HeadUser, on_request(), on_test_start(), on_test_stop(), PlacecomUser, SharedApiUser (+6 more)

### Community 9 - "Quiz & Exam Core (Faculty/Student APIs, Authz, Quiz Tables)"
Cohesion: 0.06
Nodes (60): head_can_view_quiz(), json_fail(), require_eligible_quiz(), require_login(), require_own_attempt(), require_owned_attempt_as_faculty(), require_owned_question(), require_owned_quiz() (+52 more)

### Community 10 - "PHP Tests: Admin & Test Harness"
Cohesion: 0.30
Nodes (4): AdminTest, get_test_pdo(), assert_in(), assert_true()

### Community 13 - "Admin, Academic Hierarchy & Page Navigation"
Cohesion: 0.06
Nodes (56): get_asset_url(), get_base_url(), redirect(), admins (table), courses (table), faculties (table), heads (table), placecom_officers (table) (+48 more)

### Community 14 - "PHP Tests: TestClient & Login"
Cohesion: 0.11
Nodes (7): AuthTest, {closure#1}(), {closure#2}(), {closure#3}(), {closure#4}(), {closure#5}(), TestClient

### Community 15 - "test.php"
Cohesion: 0.17
Nodes (5): bootstrap_cleanup(), bootstrap_setup(), bootstrap_teardown(), colorize(), test()

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
Cohesion: 0.50
Nodes (4): Backups, 🗄️ Database, Fresh install, Upgrading an existing database

### Community 29 - "🧪 Testing"
Cohesion: 0.50
Nodes (4): Continuous integration, Load testing, Test suite, 🧪 Testing

## Knowledge Gaps
- **10 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `redirect()` connect `Admin, Academic Hierarchy & Page Navigation` to `Quiz & Exam Core (Faculty/Student APIs, Authz, Quiz Tables)`, `PHP Tests: Security / Authz`?**
  _High betweenness centrality (0.040) - this node is a cross-community bridge._
- **Are the 7 inferred relationships involving `redirect()` (e.g. with `require_eligible_quiz()` and `require_login()`) actually correct?**
  _`redirect()` has 7 INFERRED edges - model-reasoned connections that need verification._
- **Should `PHP Tests: Shared & HTTP Client` be split into smaller, more focused modules?**
  _Cohesion score 0.12100840336134454 - nodes in this community are weakly interconnected._
- **Why does `NMIMS Quiz App 📚` connect `Docs: README` to `🌐 Deploying on Apache`, `NMIMS Quiz App — Locust Load Tests`, `🔌 API reference`, `🌟 Features`, `📖 Using the app`, `⚙️ Configuration`, `🗄️ Database`, `🧪 Testing`?**
  _High betweenness centrality (0.034) - this node is a cross-community bridge._
- **Are the 54 inferred relationships involving `assert_eq()` (e.g. with `.testDashboardStats()` and `.testDashboardStatsGuest()`) actually correct?**
  _`assert_eq()` has 54 INFERRED edges - model-reasoned connections that need verification._
- **Should `Frontend JS: Exam Engine & Proctoring` be split into smaller, more focused modules?**
  _Cohesion score 0.09523809523809523 - nodes in this community are weakly interconnected._
- **Why does `assert_eq()` connect `FacultyTest.php` to `PHP Tests: Shared & HTTP Client`, `StudentTest`, `PHP Tests: Security / Authz`, `PHP Tests: Admin & Test Harness`, `PHP Tests: TestClient & Login`, `test.php`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._