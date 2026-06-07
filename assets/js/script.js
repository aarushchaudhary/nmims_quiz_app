/*
 * script.js
 * Merged file containing:
 * - Login page logic (from login.js)
 * - Exam page logic (from exam_logic.js)
 * Both modules are independent and wrapped in DOMContentLoaded listeners.
 */

// --- Dynamic Base URL Configuration ---
// This allows the app to work on different environments:
// - Built-in server (localhost:8080): /
// - XAMPP subdirectory: /nmims_quiz_app/
// - Production: your actual domain
const BASE_URL = window.location.pathname.includes('nmims_quiz_app') ? '/nmims_quiz_app/' : '/';

/* ========== LOGIN MODULE ========== */
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const messageBox = document.getElementById('message-box');

    // Only run login logic if login form exists
    if (loginForm) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(loginForm);
            const data = Object.fromEntries(formData.entries());

            try {
                const response = await fetch(BASE_URL + 'api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    window.location.href = BASE_URL + 'index.php';
                } else if (result.status === 'conflict') {
                    // Show a confirmation dialog
                    if (confirm(result.message)) {
                        // If user clicks OK, send a "force login" request
                        forceLogin(data);
                    }
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                messageBox.textContent = `Error: ${error.message}`;
                messageBox.style.display = 'block';
            }
        });

        async function forceLogin(data) {
            data.force = true; // Add the force flag
            try {
                const response = await fetch(BASE_URL + 'api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.status === 'success') {
                    window.location.href = BASE_URL + 'index.php';
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                messageBox.textContent = `Error: ${error.message}`;
                messageBox.style.display = 'block';
            }
        }
    }
});

/* ========== EXAM MODULE (JEE/GATE-style) ========== */
document.addEventListener('DOMContentLoaded', async function() {
    // --- UI Element References ---
    const ui = {
        examContainer: document.getElementById('exam-container'),
        loadingOverlay: document.getElementById('loading-overlay'),
        warningOverlay: document.getElementById('warning-overlay'),
        warningCountSpan: document.getElementById('warning-count'),
        questionLabel: document.getElementById('question-label'),
        questionMarkingScheme: document.getElementById('question-marking-scheme'),
        timer: document.getElementById('timer'),
        questionText: document.getElementById('question-text'),
        optionsGrid: document.getElementById('options-grid'),
        paletteGrid: document.getElementById('palette-grid'),
        clickPrompt: document.getElementById('click-prompt'),
        // Action buttons
        saveNextBtn: document.getElementById('save-next-btn'),
        saveMarkBtn: document.getElementById('save-mark-btn'),
        clearBtn: document.getElementById('clear-btn'),
        markNextBtn: document.getElementById('mark-next-btn'),
        backBtn: document.getElementById('back-btn'),
        nextBtn: document.getElementById('next-btn'),
        submitBtn: document.getElementById('submit-btn'),
        // Submit modal
        submitModal: document.getElementById('submit-modal'),
        submitSummary: document.getElementById('submit-summary'),
        submitCancelBtn: document.getElementById('submit-cancel-btn'),
        submitConfirmBtn: document.getElementById('submit-confirm-btn'),
        // Legend counts
        countNotVisited: document.getElementById('count-not-visited'),
        countNotAnswered: document.getElementById('count-not-answered'),
        countAnswered: document.getElementById('count-answered'),
        countMarked: document.getElementById('count-marked'),
        countAnsweredMarked: document.getElementById('count-answered-marked')
    };

    // Only run exam logic if exam container exists
    if (!ui.examContainer) return;

    const quizId = ui.examContainer.dataset.quizId;

    // --- Exam State ---
    const examState = {
        questions: [],
        currentQuestionIndex: 0,
        attemptId: null,
        questionStartTime: null,
        // Per-question tracking arrays (initialized in startExam)
        answers: [],   // Array of { selectedOptionIds: [], answerText: '' }
        statuses: []   // Array of 'not_visited' | 'not_answered' | 'answered' | 'marked' | 'answered_marked'
    };

    const proctoringState = { warningCount: 0, examFinished: false };

    // =============================================
    // CORE FUNCTIONS
    // =============================================

    async function startExam() {
        try {
            const response = await fetch(`${BASE_URL}api/student/fetch_exam_questions.php?id=${quizId}`);
            const data = await response.json();
            if (!response.ok || data.error) {
                throw new Error(data.error || 'Failed to load exam data.');
            }
            examState.questions = data.questions;
            examState.attemptId = data.attempt_id;
            examState.config = data.config || null;

            if (examState.questions.length === 0) {
                throw new Error('This quiz has no questions. Please contact your faculty.');
            }

            // Initialize per-question state
            examState.answers = examState.questions.map(() => ({ selectedOptionIds: [], answerText: '' }));
            examState.statuses = examState.questions.map(() => 'not_visited');

            // Build the question palette
            buildPalette();

            // Mark first question as visited
            examState.statuses[0] = 'not_answered';

            // Start
            startTimer(data.remaining_seconds);
            renderQuestion();
            updatePalette();
            setInterval(checkQuizStatus, 10000);
            
            if (examState.config && examState.config.allow_calculator) {
                document.getElementById('calculator-btn').style.display = 'block';
                initCalculator();
            }
            
            ui.loadingOverlay.style.display = 'none';
            ui.examContainer.style.display = 'grid';

        } catch (error) {
            alert(`Error starting exam: ${error.message}`);
            window.location.href = 'dashboard.php';
        }
    }

    // =============================================
    // RENDERING
    // =============================================

    function renderQuestion() {
        const idx = examState.currentQuestionIndex;
        const q = examState.questions[idx];
        const savedAnswer = examState.answers[idx];

        ui.questionLabel.textContent = `Question ${idx + 1}:`;
        
        // Show marking scheme (points and negative marks)
        const points = q.points ? parseFloat(q.points) : 1;
        let negMarksStr = '';
        if (examState.config && examState.config.enable_negative_marking) {
            let negVal = 0;
            if (q.question_type_id == 1) negVal = examState.config.negative_marks_mcq;
            else if (q.question_type_id == 2) negVal = examState.config.negative_marks_msq;
            else if (q.question_type_id == 3) negVal = examState.config.negative_marks_descriptive;
            
            if (negVal > 0) negMarksStr = `, -${negVal}`;
        }
        ui.questionMarkingScheme.textContent = `Marks: +${points}${negMarksStr}`;
        
        ui.questionText.textContent = q.question_text;
        ui.optionsGrid.innerHTML = '';

        if (q.question_type_id == 3) {
            // Descriptive question
            const textarea = document.createElement('textarea');
            textarea.id = 'descriptive-answer';
            textarea.className = 'descriptive-answer-area';
            textarea.placeholder = 'Type your answer here...';
            textarea.spellcheck = false;
            textarea.setAttribute('data-gramm', 'false');
            textarea.value = savedAnswer.answerText || '';
            ui.optionsGrid.appendChild(textarea);
        } else {
            // MCQ or Multi-select
            const inputType = q.question_type_id == 1 ? 'radio' : 'checkbox';
            q.options.forEach(opt => {
                const label = document.createElement('label');
                label.className = 'option-label';
                const isChecked = savedAnswer.selectedOptionIds.includes(String(opt.id)) ? 'checked' : '';
                label.innerHTML = `<input type="${inputType}" name="option" value="${opt.id}" ${isChecked}> <span>${opt.option_text}</span>`;
                ui.optionsGrid.appendChild(label);
            });
        }

        // Update back/next button states
        ui.backBtn.disabled = idx === 0;

        examState.questionStartTime = Date.now();
        updatePalette();
    }

    function buildPalette() {
        ui.paletteGrid.innerHTML = '';
        examState.questions.forEach((q, i) => {
            const btn = document.createElement('button');
            btn.className = 'palette-btn';
            btn.textContent = String(i + 1).padStart(2, '0');
            btn.addEventListener('click', () => navigateTo(i));
            ui.paletteGrid.appendChild(btn);
        });
    }

    function updatePalette() {
        const buttons = ui.paletteGrid.querySelectorAll('.palette-btn');
        const counts = { not_visited: 0, not_answered: 0, answered: 0, marked: 0, answered_marked: 0 };

        buttons.forEach((btn, i) => {
            // Remove all state classes
            btn.className = 'palette-btn';
            const status = examState.statuses[i];
            btn.classList.add(`state-${status.replace('_', '-').replace('_', '-')}`);

            // Highlight current question
            if (i === examState.currentQuestionIndex) {
                btn.classList.add('active');
            }

            // Count statuses
            counts[status]++;
        });

        // Update legend counts
        ui.countNotVisited.textContent = counts.not_visited;
        ui.countNotAnswered.textContent = counts.not_answered;
        ui.countAnswered.textContent = counts.answered;
        ui.countMarked.textContent = counts.marked;
        ui.countAnsweredMarked.textContent = counts.answered_marked;
    }

    // =============================================
    // ANSWER MANAGEMENT
    // =============================================

    function captureCurrentAnswer() {
        const idx = examState.currentQuestionIndex;
        const q = examState.questions[idx];

        if (q.question_type_id == 3) {
            const textarea = document.getElementById('descriptive-answer');
            examState.answers[idx].answerText = textarea ? textarea.value : '';
        } else {
            examState.answers[idx].selectedOptionIds = Array.from(
                ui.optionsGrid.querySelectorAll('input:checked')
            ).map(i => i.value);
        }
    }

    function hasAnswer(idx) {
        const q = examState.questions[idx];
        const a = examState.answers[idx];
        if (q.question_type_id == 3) {
            return a.answerText.trim() !== '';
        }
        return a.selectedOptionIds.length > 0;
    }

    async function saveAnswerToServer(idx) {
        if (proctoringState.examFinished) return;
        const q = examState.questions[idx];
        const a = examState.answers[idx];
        const timeSpent = Math.round((Date.now() - examState.questionStartTime) / 1000);

        const payload = {
            attempt_id: examState.attemptId,
            question_id: q.id,
            time_spent: timeSpent,
            selected_option_ids: a.selectedOptionIds,
            answer_text: a.answerText || ''
        };

        try {
            const response = await fetch(BASE_URL + 'api/student/save_answer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.error || 'Failed to save answer.');
            }
        } catch (error) {
            console.error(error);
            alert(`Error saving answer: ${error.message}`);
        }
    }

    // =============================================
    // NAVIGATION
    // =============================================

    async function navigateTo(targetIndex) {
        if (targetIndex < 0 || targetIndex >= examState.questions.length) return;
        if (targetIndex === examState.currentQuestionIndex) return;

        // Capture current answer before leaving
        captureCurrentAnswer();

        // If leaving a not_visited question, mark it as not_answered
        const currentStatus = examState.statuses[examState.currentQuestionIndex];
        if (currentStatus === 'not_visited') {
            examState.statuses[examState.currentQuestionIndex] = 'not_answered';
        }

        // Move to target
        examState.currentQuestionIndex = targetIndex;

        // Mark target as visited if it was not_visited
        if (examState.statuses[targetIndex] === 'not_visited') {
            examState.statuses[targetIndex] = 'not_answered';
        }

        renderQuestion();
    }

    async function saveAndNext() {
        captureCurrentAnswer();
        const idx = examState.currentQuestionIndex;

        if (hasAnswer(idx)) {
            examState.statuses[idx] = 'answered';
            await saveAnswerToServer(idx);
        } else {
            examState.statuses[idx] = 'not_answered';
        }

        // Move to next question (or stay if at the end)
        if (idx < examState.questions.length - 1) {
            examState.currentQuestionIndex = idx + 1;
            if (examState.statuses[idx + 1] === 'not_visited') {
                examState.statuses[idx + 1] = 'not_answered';
            }
        }
        renderQuestion();
    }

    async function saveAndMarkForReview() {
        captureCurrentAnswer();
        const idx = examState.currentQuestionIndex;

        if (hasAnswer(idx)) {
            examState.statuses[idx] = 'answered_marked';
            await saveAnswerToServer(idx);
        } else {
            examState.statuses[idx] = 'marked';
        }

        // Move to next
        if (idx < examState.questions.length - 1) {
            examState.currentQuestionIndex = idx + 1;
            if (examState.statuses[idx + 1] === 'not_visited') {
                examState.statuses[idx + 1] = 'not_answered';
            }
        }
        renderQuestion();
    }

    function markForReviewAndNext() {
        captureCurrentAnswer();
        const idx = examState.currentQuestionIndex;

        // Mark for review, preserving answer status
        if (hasAnswer(idx)) {
            examState.statuses[idx] = 'answered_marked';
        } else {
            examState.statuses[idx] = 'marked';
        }

        // Move to next
        if (idx < examState.questions.length - 1) {
            examState.currentQuestionIndex = idx + 1;
            if (examState.statuses[idx + 1] === 'not_visited') {
                examState.statuses[idx + 1] = 'not_answered';
            }
        }
        renderQuestion();
    }

    function clearResponse() {
        const idx = examState.currentQuestionIndex;
        const q = examState.questions[idx];

        // Clear the saved answer
        examState.answers[idx] = { selectedOptionIds: [], answerText: '' };

        // Clear UI
        if (q.question_type_id == 3) {
            const textarea = document.getElementById('descriptive-answer');
            if (textarea) textarea.value = '';
        } else {
            ui.optionsGrid.querySelectorAll('input:checked').forEach(inp => inp.checked = false);
        }

        // Reset status (keep marked state if it was marked)
        const currentStatus = examState.statuses[idx];
        if (currentStatus === 'answered_marked' || currentStatus === 'marked') {
            examState.statuses[idx] = 'marked';
        } else {
            examState.statuses[idx] = 'not_answered';
        }

        updatePalette();
    }

    // =============================================
    // SUBMIT
    // =============================================

    function showSubmitModal() {
        // Build summary
        const counts = { not_visited: 0, not_answered: 0, answered: 0, marked: 0, answered_marked: 0 };
        examState.statuses.forEach(s => counts[s]++);

        ui.submitSummary.innerHTML = `
            <div style="font-size: 16px; margin-bottom: 15px;"><strong>Summary:</strong></div>
            <div class="palette-legend" style="border: none; padding: 0; background: none;">
                <div class="legend-row">
                    <span class="legend-indicator legend-not-visited">${counts.not_visited}</span>
                    <span class="legend-text">Not Visited</span>
                </div>
                <div class="legend-row">
                    <span class="legend-indicator legend-not-answered">${counts.not_answered}</span>
                    <span class="legend-text">Not Answered</span>
                </div>
                <div class="legend-row">
                    <span class="legend-indicator legend-answered">${counts.answered}</span>
                    <span class="legend-text">Answered</span>
                </div>
                <div class="legend-row">
                    <span class="legend-indicator legend-marked">${counts.marked}</span>
                    <span class="legend-text">Marked for Review</span>
                </div>
                <div class="legend-row">
                    <span class="legend-indicator legend-answered-marked">${counts.answered_marked}</span>
                    <span class="legend-text">Answered & Marked for Review</span>
                </div>
            </div>
        `;

        ui.submitModal.classList.add('show');
    }

    async function finishExam(isDisqualified = false) {
        if (proctoringState.examFinished) return;
        proctoringState.examFinished = true;

        // Save current question before submitting
        captureCurrentAnswer();
        const idx = examState.currentQuestionIndex;
        if (hasAnswer(idx) && !isDisqualified) {
            await saveAnswerToServer(idx);
        }

        let bodyPayload = {
            attempt_id: examState.attemptId,
            is_disqualified: isDisqualified
        };

        try {
            await fetch(BASE_URL + 'api/student/finish_exam.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(bodyPayload)
            });

            if (isDisqualified) {
                window.location.href = `disqualified.php?attempt_id=${examState.attemptId}`;
            } else {
                alert('Exam Finished! Your answers have been submitted.');
                window.location.href = `results.php?attempt_id=${examState.attemptId}`;
            }
        } catch (error) {
            alert('Could not submit exam. Please check your connection.');
            proctoringState.examFinished = false;
        }
    }

    // =============================================
    // TIMER
    // =============================================

    function startTimer(totalSeconds) {
        let timeLeft = parseInt(totalSeconds, 10) || 0;
        const timerInterval = setInterval(() => {
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                if (!proctoringState.examFinished) {
                    ui.timer.textContent = 'Time Up!';
                    alert('Time is up! Submitting your exam automatically.');
                    finishExam();
                }
                return;
            }
            timeLeft--;
            const minutes = Math.floor(timeLeft / 60);
            let seconds = timeLeft % 60;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            ui.timer.textContent = `Time Left: ${minutes}:${seconds}`;
        }, 1000);
    }

    // =============================================
    // STATUS CHECK
    // =============================================

    async function checkQuizStatus() {
        if (proctoringState.examFinished) return;
        try {
            const response = await fetch(`${BASE_URL}api/shared/get_quiz_status.php?id=${quizId}`);
            const data = await response.json();
            if (data.status && data.status !== 'In Progress') {
                alert('The faculty has ended the exam. Your answers will now be submitted.');
                finishExam();
            }
        } catch (error) {
            console.error('Could not check quiz status:', error);
        }
    }

    // =============================================
    // EVENT LISTENERS
    // =============================================

    ui.saveNextBtn.addEventListener('click', saveAndNext);
    ui.saveMarkBtn.addEventListener('click', saveAndMarkForReview);
    ui.clearBtn.addEventListener('click', clearResponse);
    ui.markNextBtn.addEventListener('click', markForReviewAndNext);

    ui.backBtn.addEventListener('click', () => {
        navigateTo(examState.currentQuestionIndex - 1);
    });

    ui.nextBtn.addEventListener('click', () => {
        navigateTo(examState.currentQuestionIndex + 1);
    });

    ui.submitBtn.addEventListener('click', () => {
        captureCurrentAnswer();
        showSubmitModal();
    });

    ui.submitCancelBtn.addEventListener('click', () => {
        ui.submitModal.classList.remove('show');
    });

    ui.submitConfirmBtn.addEventListener('click', () => {
        ui.submitModal.classList.remove('show');
        finishExam();
    });

    // =============================================
    // INITIALIZER
    // =============================================

    async function initializeExam() {
        document.body.classList.add('exam-mode');
        if (ui.clickPrompt) {
            ui.clickPrompt.textContent = 'Click anywhere to begin the exam.';
        }

        document.body.addEventListener('click', async () => {
            await startExam();
        }, { once: true });
    }

    // =============================================
    // CALCULATOR
    // =============================================
    function initCalculator() {
        const calcBtn = document.getElementById('calculator-btn');
        const calcUI = document.getElementById('calculator-ui');
        const calcClose = document.getElementById('calc-close');
        const calcHeader = document.getElementById('calc-header');
        const display = document.getElementById('calc-display');
        
        if (!calcBtn || !calcUI) return;

        // Toggle visibility
        calcBtn.addEventListener('click', () => {
            calcUI.style.display = calcUI.style.display === 'none' ? 'block' : 'none';
        });

        calcClose.addEventListener('click', () => {
            calcUI.style.display = 'none';
        });

        // Draggable logic
        let isDragging = false;
        let currentX;
        let currentY;
        let initialX;
        let initialY;
        let xOffset = 0;
        let yOffset = 0;

        calcHeader.addEventListener('mousedown', dragStart);
        document.addEventListener('mouseup', dragEnd);
        document.addEventListener('mousemove', drag);

        function dragStart(e) {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
            if (e.target === calcHeader || e.target.parentNode === calcHeader) {
                isDragging = true;
            }
        }
        function dragEnd(e) {
            initialX = currentX;
            initialY = currentY;
            isDragging = false;
        }
        function drag(e) {
            if (isDragging) {
                e.preventDefault();
                currentX = e.clientX - initialX;
                currentY = e.clientY - initialY;
                xOffset = currentX;
                yOffset = currentY;
                calcUI.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;
            }
        }

        // Calculator Logic
        let currentInput = '';
        document.querySelectorAll('.calc-btn[data-val]').forEach(btn => {
            btn.addEventListener('click', () => {
                currentInput += btn.dataset.val;
                display.value = currentInput;
            });
        });

        document.getElementById('calc-clear').addEventListener('click', () => {
            currentInput = '';
            display.value = '';
        });

        document.getElementById('calc-equals').addEventListener('click', () => {
            try {
                if (!currentInput) return;
                // Safe eval replacement for basic math
                const result = new Function('return ' + currentInput)();
                display.value = Number.isFinite(result) ? result : 'Error';
                currentInput = display.value !== 'Error' ? String(result) : '';
            } catch (e) {
                display.value = 'Error';
                currentInput = '';
            }
        });

        // Evaluate first, then apply func
        function applyUnaryOp(func) {
            try {
                if (!currentInput) {
                    if (display.value && display.value !== 'Error') {
                        currentInput = display.value;
                    } else {
                        return;
                    }
                }
                const result = new Function('return ' + currentInput)();
                if (!Number.isFinite(result)) throw new Error();
                const newResult = func(result);
                display.value = Number.isFinite(newResult) ? newResult : 'Error';
                currentInput = display.value !== 'Error' ? String(newResult) : '';
            } catch (e) {
                display.value = 'Error';
                currentInput = '';
            }
        }

        document.getElementById('calc-sqrt').addEventListener('click', () => {
            applyUnaryOp(val => Math.sqrt(val));
        });

        document.getElementById('calc-percent').addEventListener('click', () => {
            applyUnaryOp(val => val / 100);
        });

        document.getElementById('calc-neg').addEventListener('click', () => {
            applyUnaryOp(val => val * -1);
        });
    }

    if (quizId) {
        initializeExam();
    } else {
        window.location.href = 'dashboard.php';
    }
});
