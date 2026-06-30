<?php
  $pageTitle = 'Faculty Guide - How to Use';
  require_once '../../assets/templates/header.php';
  
  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
      header('Location: ../../login.php');
      exit();
  }
?>

<div class="manage-container">
    <h2 style="text-align: center; margin-bottom: 30px;">Faculty Guide & Documentation</h2>
    
    <div class="section-box" style="margin-bottom: 30px; padding: 25px;">
        <h3 style="border-bottom: 2px solid #e60000; padding-bottom: 10px; margin-bottom: 20px; color: #333;">Glossary of Terms</h3>
        <ul style="line-height: 1.8; color: #444; font-size: 1.05em;">
            <li><strong>MCQ (Multiple Choice Question):</strong> A question with multiple options where only <em>one</em> option is correct.</li>
            <li><strong>MSQ (Multiple Select Question):</strong> A question where <em>multiple</em> options can be correct. Students must select all correct options.</li>
            <li><strong>Descriptive Question:</strong> A long-form text question that requires manual evaluation by the faculty.</li>
            <li><strong>Item Analysis:</strong> A statistical report showing how students performed on individual questions (difficulty index, discrimination index).</li>
            <li><strong>Publish Results:</strong> Releasing the final scores and answers to the students so they can view them on their dashboard.</li>
            <li><strong>SAP ID:</strong> The unique student identification number used for manual enrollment.</li>
        </ul>
    </div>
    
    <div class="section-box" style="margin-bottom: 30px; padding: 25px;">
        <h3 style="border-bottom: 2px solid #e60000; padding-bottom: 10px; margin-bottom: 25px; color: #333;">Step-by-Step Task Guides</h3>
        
        <div style="margin-bottom: 30px;">
            <h4 style="color: #0056b3; margin-bottom: 15px;">1. How to Create a New Quiz</h4>
            <ol style="line-height: 1.7; color: #444;">
                <li>Go to the <strong>Manage Quizzes</strong> page and click <a href="create_quiz.php" class="button-red" style="padding: 4px 8px; font-size: 0.85em; text-decoration: none; margin: 0 5px;">Create New Quiz</a>.</li>
                <li>Fill in the Quiz Title, School, Course, Start/End Time, and Duration.</li>
                <li>Select the target audience by checking the appropriate Sections, Batches, Electives, or Re-exam groups.</li>
                <li><em>Optional:</em> Enter specific student SAP IDs separated by commas to manually invite them.</li>
                <li>Specify how many Easy, Medium, and Hard questions the quiz should have.</li>
                <li>Toggle advanced settings: Calculator allowance, Negative Marking, and Instant Results.</li>
                <li>Click <strong>Create Quiz</strong>.</li>
            </ol>
        </div>

        <div style="margin-bottom: 30px;">
            <h4 style="color: #0056b3; margin-bottom: 15px;">2. How to Add Questions to a Quiz</h4>
            <ol style="line-height: 1.7; color: #444;">
                <li>After creating a quiz, click <strong>"Manage Questions"</strong> on the quiz card.</li>
                <li>Click <strong>"View & Manage Existing Questions"</strong> -> <strong>"+ Add More Ques"</strong> to open the question addition form.</li>
                <li>Select the Question Type (MCQ, MSQ, Descriptive) and Difficulty (Easy, Medium, Hard).</li>
                <li>Type the question text. For MCQ/MSQ, enter the options and use the radio/checkbox to mark the correct answer(s).</li>
                <li>Click <strong>"Add Question"</strong>. Repeat this until you have satisfied the Easy/Medium/Hard quota you set during quiz creation.</li>
            </ol>
        </div>

        <div style="margin-bottom: 30px;">
            <h4 style="color: #0056b3; margin-bottom: 15px;">3. How to Evaluate Descriptive Answers</h4>
            <ol style="line-height: 1.7; color: #444;">
                <li>Once a quiz has ended, go to the <strong>Manage Quizzes</strong> page.</li>
                <li>If the quiz contained descriptive questions, click <strong>"Evaluate Answers"</strong>.</li>
                <li>You will see a list of students and their answers. Read the answer and enter the marks awarded out of the maximum points.</li>
                <li>Save your evaluations. You must evaluate all descriptive answers before you can publish the results.</li>
            </ol>
        </div>

        <div style="margin-bottom: 30px;">
            <h4 style="color: #0056b3; margin-bottom: 15px;">4. How to Publish Results to Students</h4>
            <ol style="line-height: 1.7; color: #444;">
                <li>Wait for the quiz to end (Status: Completed).</li>
                <li>Ensure all descriptive answers (if any) are evaluated.</li>
                <li>On the quiz card, click the green <strong>"Publish Results"</strong> button.</li>
                <li>Students will immediately be able to see their scores and the correct answers on their dashboard.</li>
                <li>If you made a mistake, you can click the red <strong>"Unpublish Results"</strong> button to hide them again.</li>
            </ol>
        </div>

        <div>
            <h4 style="color: #0056b3; margin-bottom: 15px;">5. How to View and Export Reports</h4>
            <ol style="line-height: 1.7; color: #444;">
                <li>Click <strong>"View Reports"</strong> on any quiz card.</li>
                <li>You will see a summary of Total Attempts, Average Score, and Disqualified students.</li>
                <li>Click <strong>"Export to Excel"</strong> to download a comprehensive CSV file containing all student scores and details.</li>
                <li>Click <strong>"Item Analysis"</strong> to view statistical data on question difficulty and student performance.</li>
            </ol>
        </div>
    </div>
</div>

<?php require_once '../../assets/templates/footer.php'; ?>
