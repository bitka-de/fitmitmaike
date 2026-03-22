document.addEventListener('DOMContentLoaded', function () {
    const mediaInput = document.getElementById('mediaInput');
    const uploadForm = document.getElementById('uploadForm');
    const bulkbar = document.getElementById('bulkbar');
    const selectedCount = document.getElementById('selectedCount');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const clearSelectionBtn = document.getElementById('clearSelectionBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const bulkActionForm = document.getElementById('bulkActionForm');

    const dragMoveForm = document.getElementById('dragMoveForm');
    const dragMoveFile = document.getElementById('dragMoveFile');
    const dragMoveTargetFolder = document.getElementById('dragMoveTargetFolder');

    const deleteFolderModal = document.getElementById('deleteFolderModal');
    const deleteFolderName = document.getElementById('deleteFolderName');
    const deleteFolderMode = document.getElementById('deleteFolderMode');
    const deleteFolderForm = document.getElementById('deleteFolderForm');

    let draggedFileName = null;

    function updateBulkBar() {
        const allChecks = document.querySelectorAll('.select-box');
        const checkedChecks = document.querySelectorAll('.select-box:checked');

        if (selectedCount) {
            selectedCount.textContent = String(checkedChecks.length);
        }

        if (bulkbar) {
            bulkbar.classList.toggle('is-active', checkedChecks.length > 0);
        }

        if (selectAllBtn) {
            selectAllBtn.disabled = allChecks.length === 0;
        }

        if (clearSelectionBtn) {
            clearSelectionBtn.disabled = checkedChecks.length === 0;
        }
    }

    function closeAllBoxes() {
        document.querySelectorAll('.rename-box, .move-box').forEach((box) => {
            box.classList.remove('is-active');
        });
    }

    function toggleBox(id, groupClass) {
        const current = document.getElementById(id);
        if (!current) return;

        document.querySelectorAll('.' + groupClass).forEach((box) => {
            if (box.id !== id) {
                box.classList.remove('is-active');
            }
        });

        current.classList.toggle('is-active');
    }

    window.openDeleteFolderModal = function (folderName) {
        if (!deleteFolderModal || !deleteFolderName) return;
        deleteFolderName.value = folderName;
        deleteFolderModal.classList.add('is-active');
    };

    window.closeDeleteFolderModal = function () {
        if (!deleteFolderModal) return;
        deleteFolderModal.classList.remove('is-active');
    };

    function submitDeleteFolder(mode) {
        if (!deleteFolderMode || !deleteFolderForm) return;
        deleteFolderMode.value = mode;
        deleteFolderForm.submit();
    }

    mediaInput?.addEventListener('change', function () {
        if (this.files && this.files.length > 0) {
            uploadForm?.submit();
        }
    });

    document.querySelectorAll('.select-box').forEach((box) => {
        box.addEventListener('change', updateBulkBar);
    });

    selectAllBtn?.addEventListener('click', function () {
        document.querySelectorAll('.select-box').forEach((box) => {
            box.checked = true;
        });
        updateBulkBar();
    });

    clearSelectionBtn?.addEventListener('click', function () {
        document.querySelectorAll('.select-box').forEach((box) => {
            box.checked = false;
        });
        updateBulkBar();
    });

    bulkDeleteBtn?.addEventListener('click', function () {
        const hasSelected = document.querySelectorAll('.select-box:checked').length > 0;
        if (!hasSelected) return;

        if (confirm('Ausgewählte Dateien wirklich löschen?')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'bulk_delete';
            input.value = '1';
            bulkActionForm?.appendChild(input);
            bulkActionForm?.submit();
        }
    });

    document.querySelectorAll('[data-toggle-box]').forEach((button) => {
        button.addEventListener('click', function () {
            const boxId = this.getAttribute('data-toggle-box');
            const groupClass = this.getAttribute('data-group-class');

            if (!boxId || !groupClass) return;
            toggleBox(boxId, groupClass);
        });
    });

    document.querySelectorAll('[data-folder-delete-mode]').forEach((button) => {
        button.addEventListener('click', function () {
            const mode = this.getAttribute('data-folder-delete-mode');
            if (!mode) return;
            submitDeleteFolder(mode);
        });
    });

    document.querySelectorAll('[data-close-folder-modal]').forEach((button) => {
        button.addEventListener('click', function () {
            window.closeDeleteFolderModal();
        });
    });

    deleteFolderModal?.addEventListener('click', function (e) {
        if (e.target === deleteFolderModal) {
            window.closeDeleteFolderModal();
        }
    });

    document.querySelectorAll('.media-card[draggable="true"]').forEach((card) => {
        card.addEventListener('dragstart', function (e) {
            draggedFileName = this.dataset.fileName || null;
            this.classList.add('is-dragging');

            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggedFileName || '');
            }
        });

        card.addEventListener('dragend', function () {
            this.classList.remove('is-dragging');
            document.querySelectorAll('.folder-link').forEach((folder) => {
                folder.classList.remove('is-drag-over');
            });
        });
    });

    document.querySelectorAll('.folder-link').forEach((folder) => {
        folder.addEventListener('dragover', function (e) {
            e.preventDefault();
            this.classList.add('is-drag-over');

            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'move';
            }
        });

        folder.addEventListener('dragleave', function () {
            this.classList.remove('is-drag-over');
        });

        folder.addEventListener('drop', function (e) {
            e.preventDefault();
            this.classList.remove('is-drag-over');

            const targetFolder = this.dataset.folder ?? '';
            const fileName = draggedFileName || (e.dataTransfer ? e.dataTransfer.getData('text/plain') : '');

            if (!fileName || !dragMoveForm || !dragMoveFile || !dragMoveTargetFolder) {
                return;
            }

            dragMoveFile.value = fileName;
            dragMoveTargetFolder.value = targetFolder;
            dragMoveForm.submit();
        });
    });

    closeAllBoxes();
    updateBulkBar();
});