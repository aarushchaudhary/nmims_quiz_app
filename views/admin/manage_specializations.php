<?php
  $pageTitle = 'Manage Specializations';
  require_once '../../assets/templates/header.php';
  require_once '../../config/database.php';

  if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
      redirect('login.php');
      exit();
  }
  $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name ASC")->fetchAll();
?>

<style>
    .page-center-container { display: flex; flex-direction: column; align-items: center; gap: 40px; }
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
    .modal-content { background-color: #fefefe; margin: auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 700px; border-radius: 8px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2); }
    .close-button { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    #sap-id-display { border: 1px solid #ccc; padding: 10px; min-height: 100px; max-height: 200px; overflow-y: auto; background-color: #f9f9f9; border-radius: 4px; margin-top: 10px; }
    #sap-id-display span { display: inline-block; background-color: #e9e9e9; padding: 2px 8px; border-radius: 4px; margin: 2px; font-size: 0.9em; }
</style>

<div class="page-center-container">

    <div class="form-container">
        <h2>Add New Specialization</h2>
        <form id="add-specialization-form">
            <div class="form-group"><label for="school_id">School</label><select id="school_id" name="school_id" required><option value="" disabled selected>-- Select a School --</option><?php foreach ($schools as $school): ?><option value="<?php echo $school['id']; ?>"><?php echo htmlspecialchars($school['name']); ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label for="name">Specialization Name</label><input type="text" id="name" name="name" placeholder="e.g., Marketing, Finance" required></div>
            <div class="form-group"><label for="description">Description (Optional)</label><textarea id="description" name="description" rows="3"></textarea></div>
            <div class="form-group" style="text-align: center;"><button type="submit" class="button-red">Add Specialization</button></div>
        </form>
    </div>

    <div class="manage-container">
        <h2>Existing Specializations</h2>
        <table class="data-table">
            <thead><tr><th>Name</th><th>School</th><th>Description</th><th>Actions</th></tr></thead>
            <tbody id="specializations-table-body"></tbody>
        </table>
    </div>
</div>

<div id="assign-modal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h3 id="modal-title">Assign Students to Specialization</h3>
        <p>You can either upload an Excel file (with one column of SAP IDs) or paste a list of SAP IDs below.</p>
        <input type="hidden" id="modal-spec-id">

        <div class="form-row">
            <div class="form-group">
                <label for="sap_id_file">Upload SAP ID Excel File</label>
                <input type="file" id="sap_id_file" accept=".xlsx">
            </div>
            <div class="form-group">
                <label for="sap_id_text">Or Paste SAP IDs (comma or new-line separated)</label>
                <textarea id="sap_id_text" rows="5" style="width: 100%;"></textarea>
            </div>
        </div>
        
        <h4>Loaded SAP IDs for Assignment: (<span id="sap-count">0</span>)</h4>
        <div id="sap-id-display"></div>
        <div class="form-group" style="text-align: center; margin-top: 20px;">
            <button id="bulk-assign-btn" class="button-red">Assign to Specialization</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<script>
const BASE_URL = '<?= get_base_url() ?>';
document.addEventListener('DOMContentLoaded', function() {
    // --- Existing code for add/delete specializations (condensed for clarity) ---
    const addForm = document.getElementById('add-specialization-form');
    const tableBody = document.getElementById('specializations-table-body');
    async function loadSpecializations() {
        const response = await fetch(BASE_URL + 'api/admin/get_specializations.php');
        const specializations = await response.json();
        tableBody.innerHTML = '';
        if (specializations.length === 0) { tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No specializations found.</td></tr>'; return; }
        specializations.forEach(spec => {
            const row = `<tr>
                <td>${escapeHTML(spec.name)}</td>
                <td>${escapeHTML(spec.school_name)}</td>
                <td>${escapeHTML(spec.description) || ''}</td>
                <td class="action-buttons">
                    <button class="btn-manage btn-assign" data-id="${spec.id}" data-name="${escapeHTML(spec.name)}">Assign Students</button>
                    <button class="btn-manage btn-delete" data-id="${spec.id}">Delete</button>
                </td>
            </tr>`;
            tableBody.insertAdjacentHTML('beforeend', row);
        });
    }
    addForm.addEventListener('submit', async e => { /* existing add logic */ e.preventDefault(); const data = Object.fromEntries(new FormData(addForm).entries()); const response = await fetch(BASE_URL + 'api/admin/add_specialization.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) }); const result = await response.json(); if (result.success) { addForm.reset(); loadSpecializations(); } else { alert('Error: ' + result.message); } });
    tableBody.addEventListener('click', async e => { /* existing delete logic */ if (e.target.classList.contains('btn-delete')) { const specId = e.target.dataset.id; if (confirm('Are you sure?')) { const response = await fetch(BASE_URL + 'api/admin/delete_specialization.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: specId }) }); const result = await response.json(); if (result.success) { loadSpecializations(); } else { alert('Error: ' + result.message); } } } });
    function escapeHTML(str) { if (!str) return ''; return str.replace(/[&<>"']/g, match => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[match]); }

    // --- NEW: Code for Bulk Assignment Modal ---
    const modal = document.getElementById('assign-modal');
    const closeBtn = document.querySelector('.close-button');
    const modalTitle = document.getElementById('modal-title');
    const modalSpecId = document.getElementById('modal-spec-id');
    const sapDisplay = document.getElementById('sap-id-display');
    const sapCount = document.getElementById('sap-count');
    const fileInput = document.getElementById('sap_id_file');
    const textInput = document.getElementById('sap_id_text');
    const assignBtn = document.getElementById('bulk-assign-btn');

    let loadedSaps = new Set();

    function updateSapDisplay() {
        sapDisplay.innerHTML = '';
        loadedSaps.forEach(sap => {
            const tag = document.createElement('span');
            tag.textContent = sap;
            sapDisplay.appendChild(tag);
        });
        sapCount.textContent = loadedSaps.size;
    }

    // Open Modal
    tableBody.addEventListener('click', e => {
        if (e.target.classList.contains('btn-assign')) {
            const specId = e.target.dataset.id;
            const specName = e.target.dataset.name;
            modalTitle.textContent = `Assign Students to "${specName}"`;
            modalSpecId.value = specId;
            modal.style.display = 'flex';
        }
    });

    // Close Modal
    closeBtn.onclick = () => {
        modal.style.display = 'none';
        fileInput.value = '';
        textInput.value = '';
        loadedSaps.clear();
        updateSapDisplay();
    };
    window.onclick = e => { if (e.target == modal) closeBtn.onclick(); };

    // Load from Text Area
    textInput.addEventListener('input', () => {
        const saps = textInput.value.split(/[\s,]+/).filter(Boolean); // Split by space or comma
        loadedSaps = new Set(saps);
        updateSapDisplay();
    });

    // Load from Excel File
    fileInput.addEventListener('change', e => {
        const file = e.target.files[0];
        const reader = new FileReader();
        reader.onload = event => {
            const data = new Uint8Array(event.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const json = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
            
            loadedSaps.clear();
            json.forEach(row => {
                if (row[0]) loadedSaps.add(String(row[0]).trim());
            });
            updateSapDisplay();
        };
        reader.readAsArrayBuffer(file);
    });

    // Assign Button Click
    assignBtn.addEventListener('click', async () => {
        const specId = modalSpecId.value;
        const sapIds = Array.from(loadedSaps);

        if (sapIds.length === 0) {
            alert('No SAP IDs loaded to assign.');
            return;
        }

        assignBtn.textContent = 'Assigning...';
        assignBtn.disabled = true;

        try {
            const response = await fetch(BASE_URL + 'api/admin/bulk_assign_specialization.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ specialization_id: specId, sap_ids: sapIds })
            });
            const result = await response.json();
            alert(result.message);
            if (result.success) {
                closeBtn.onclick();
            }
        } catch (error) {
            alert('An error occurred during assignment.');
        } finally {
            assignBtn.textContent = 'Assign to Specialization';
            assignBtn.disabled = false;
        }
    });

    // Initial load
    loadSpecializations();
});
</script>

<?php
  require_once '../../assets/templates/footer.php';
?>