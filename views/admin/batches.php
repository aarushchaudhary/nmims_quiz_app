<?php
  $pageTitle = 'Manage Batches';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }

  // Fetch all classes for the dropdown
  $classes = $pdo->query("SELECT * FROM classes ORDER BY name ASC")->fetchAll();

  // Fetch batches with their associated class
  $batches = $pdo->query("
      SELECT b.*, c.name as class_name 
      FROM batches b 
      JOIN classes c ON b.class_id = c.id 
      ORDER BY c.name ASC, b.name ASC
  ")->fetchAll();
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

    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Batches (<?php echo count($batches); ?>)</h2>
    </div>

    <?php
    if (isset($_GET['success'])) { echo '<div class="message-box success-message">' . htmlspecialchars($_GET['success']) . '</div>'; }
    if (isset($_GET['error'])) { echo '<div class="message-box error-message">' . htmlspecialchars($_GET['error']) . '</div>'; }
    ?>
    
    <div class="section-box">
        <h3>Add New Batch</h3>
        <form action="<?= get_base_url() ?>api/admin/add_batch.php" method="POST" style="display:flex; flex-direction:column; gap: 15px;">
            <div style="display:flex; gap: 15px;">
                <input type="text" name="batch_name" class="input-field" placeholder="Enter batch name (e.g., B1, B2)" required style="flex: 1;">
                
                <select name="class_id" id="class_id" class="input-field" required style="flex: 1;">
                    <option value="">Select Class</option>
                    <?php foreach ($classes as $cls): ?>
                        <option value="<?php echo $cls['id']; ?>"><?php echo htmlspecialchars($cls['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:flex; gap: 15px;">
                <div class="autocomplete-container" style="flex: 1;">
                    <input type="text" id="sap_id_range_start_display" class="input-field" placeholder="Search Start SAP ID (Name/ID)" required style="width: 100%; box-sizing: border-box;" autocomplete="off" onkeyup="searchStudent(this, 'sap_id_range_start')">
                    <input type="hidden" name="sap_id_range_start" id="sap_id_range_start">
                    <div id="sap_id_range_start_results" class="autocomplete-results" style="display: none;"></div>
                </div>
                
                <div class="autocomplete-container" style="flex: 1;">
                    <input type="text" id="sap_id_range_end_display" class="input-field" placeholder="Search End SAP ID (Name/ID)" required style="width: 100%; box-sizing: border-box;" autocomplete="off" onkeyup="searchStudent(this, 'sap_id_range_end')">
                    <input type="hidden" name="sap_id_range_end" id="sap_id_range_end">
                    <div id="sap_id_range_end_results" class="autocomplete-results" style="display: none;"></div>
                </div>
            </div>

            <button type="submit" class="button-red" style="width:auto; align-self: flex-start;">Add Batch</button>
        </form>
    </div>

    <table class="data-table" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Batch Name</th>
                <th>Class</th>
                <th>SAP ID Range</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($batches as $batch): ?>
                <tr>
                    <td><?php echo htmlspecialchars($batch['name']); ?></td>
                    <td><?php echo htmlspecialchars($batch['class_name']); ?></td>
                    <td><?php echo htmlspecialchars($batch['sap_id_range_start'] . ' - ' . $batch['sap_id_range_end']); ?></td>
                    <td class="action-buttons" style="flex-direction:row;">
                        <a href="edit_batch.php?id=<?php echo $batch['id']; ?>" class="btn-edit" style="background-color:#ffc107; color:black; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none; margin-right: 5px;">Edit</a>
                        <a href="<?= get_base_url() ?>api/admin/delete_batch.php?id=<?php echo $batch['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this batch?')" style="background-color:#dc3545; color:white; border:none; padding: 8px 12px; font-size: 14px; border-radius: 6px; cursor:pointer; text-decoration:none;">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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
