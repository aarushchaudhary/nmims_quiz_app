<?php
  $pageTitle = 'Edit Batch';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  $batch_id = $_GET['id'] ?? null;
  if (!$batch_id) {
      header('Location: batches.php');
      exit();
  }

  // Fetch batch details
  $stmt = $pdo->prepare("SELECT * FROM batches WHERE id = ?");
  $stmt->execute([$batch_id]);
  $batch = $stmt->fetch();

  if (!$batch) {
      header('Location: batches.php?error=' . urlencode("Batch not found."));
      exit();
  }

  // Fetch all classes for the dropdown
  $classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();

  // Fetch student names for autocomplete display
  $start_student_name = "";
  if ($batch['sap_id_range_start']) {
      $stmt = $pdo->prepare("SELECT name FROM students WHERE sap_id = ?");
      $stmt->execute([$batch['sap_id_range_start']]);
      if ($row = $stmt->fetch()) {
          $start_student_name = $row['name'] . " (" . $batch['sap_id_range_start'] . ")";
      } else {
          $start_student_name = $batch['sap_id_range_start'];
      }
  }

  $end_student_name = "";
  if ($batch['sap_id_range_end']) {
      $stmt = $pdo->prepare("SELECT name FROM students WHERE sap_id = ?");
      $stmt->execute([$batch['sap_id_range_end']]);
      if ($row = $stmt->fetch()) {
          $end_student_name = $row['name'] . " (" . $batch['sap_id_range_end'] . ")";
      } else {
          $end_student_name = $batch['sap_id_range_end'];
      }
  }
?>

<style>
.autocomplete-container {
    position: relative;
}
.autocomplete-results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #fff;
    border: 1px solid #ccc;
    border-top: none;
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    border-radius: 0 0 4px 4px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}
.autocomplete-item {
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
}
.autocomplete-item:hover {
    background-color: #f0f0f0;
}
</style>

<div class="manage-container">

    <div class="section-box">
        <h3>Edit Batch</h3>
        <form action="<?= get_base_url() ?>api/admin/edit_batch.php" method="POST" style="display:flex; flex-direction:column; gap: 15px;">
            <input type="hidden" name="batch_id" value="<?php echo $batch['id']; ?>">
            
            <div style="display:flex; gap: 15px;">
                <input type="text" name="batch_name" class="input-field" placeholder="Enter batch name (e.g., B1, B2)" required value="<?php echo htmlspecialchars($batch['name']); ?>" style="flex: 1;">
                
                <select name="class_id" id="class_id" class="input-field" required style="flex: 1;">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?php echo $cls['id']; ?>" <?php if ($batch['class_id'] == $cls['id']) echo 'selected'; ?>><?php echo htmlspecialchars($cls['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex; gap: 15px;">
                <div class="autocomplete-container" style="flex: 1;">
                    <input type="text" id="sap_id_range_start_display" class="input-field" placeholder="Search Start SAP ID (Name/ID)" required style="width: 100%; box-sizing: border-box;" autocomplete="off" onkeyup="searchStudent(this, 'sap_id_range_start')" value="<?php echo htmlspecialchars($start_student_name); ?>">
                    <input type="hidden" name="sap_id_range_start" id="sap_id_range_start" value="<?php echo htmlspecialchars($batch['sap_id_range_start']); ?>">
                    <div id="sap_id_range_start_results" class="autocomplete-results" style="display: none;"></div>
                </div>
                
                <div class="autocomplete-container" style="flex: 1;">
                    <input type="text" id="sap_id_range_end_display" class="input-field" placeholder="Search End SAP ID (Name/ID)" required style="width: 100%; box-sizing: border-box;" autocomplete="off" onkeyup="searchStudent(this, 'sap_id_range_end')" value="<?php echo htmlspecialchars($end_student_name); ?>">
                    <input type="hidden" name="sap_id_range_end" id="sap_id_range_end" value="<?php echo htmlspecialchars($batch['sap_id_range_end']); ?>">
                    <div id="sap_id_range_end_results" class="autocomplete-results" style="display: none;"></div>
                </div>
            </div>

            <button type="submit" class="button-red" style="width:auto; align-self: flex-start;">Update Batch</button>
        </form>
    </div>
</div>

<script>
    let searchTimeout;
    function searchStudent(inputElement, hiddenId) {
        clearTimeout(searchTimeout);
        const query = inputElement.value;
        const resultsContainer = document.getElementById(hiddenId + '_results');
        const hiddenInput = document.getElementById(hiddenId);

        if (query.trim() === '') {
            hiddenInput.value = '';
            resultsContainer.style.display = 'none';
            return;
        }

        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(() => {
            fetch('<?= get_base_url() ?>api/admin/search_student.php?q=' + encodeURIComponent(query))
                .then(res => res.json())
                .then(data => {
                    resultsContainer.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(student => {
                            const div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            div.textContent = `${student.name} (${student.sap_id})`;
                            div.onclick = function() {
                                inputElement.value = `${student.name} (${student.sap_id})`;
                                hiddenInput.value = student.sap_id;
                                resultsContainer.style.display = 'none';
                            };
                            resultsContainer.appendChild(div);
                        });
                        resultsContainer.style.display = 'block';
                    } else {
                        resultsContainer.innerHTML = '<div style="padding: 8px 12px; font-size: 14px; color: #777;">No students found</div>';
                        resultsContainer.style.display = 'block';
                    }
                })
                .catch(err => console.error(err));
        }, 300);
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.autocomplete-container')) {
            document.getElementById('sap_id_range_start_results').style.display = 'none';
            document.getElementById('sap_id_range_end_results').style.display = 'none';
        }
    });
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>
