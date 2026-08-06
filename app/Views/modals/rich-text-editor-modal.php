<?php
/**
* Rich Text Editor Modal
*
* Reusable modal component for editing text fields with a full-featured editor.
*
* REQUIRED CSS: Uses app-modal.css which should be linked in main template
*
* 
* Usage:
* 1. Include this file once in your view
* 2. Add data attributes to your trigger buttons:
*    <button type="button"
*            class="btn btn-outline-secondary"
*            data-rte-trigger
*            data-rte-field="description"
*            data-rte-label="Edit Description">
*        <i class="bi bi-pencil-square"></i> Edit
*    </button>
*
* Required data attributes on trigger button:
* - data-rte-trigger: Identifies the button as a trigger
* - data-rte-field: Name of the form field to edit (must match input name attribute)
* - data-rte-label: Display label for the modal title
*
* The modal will:
* - Find the corresponding form field by name
* - Load its content into the rich text editor
* - Save changes back to the field on "Save"
* - Handle multiple calls from different fields on the same form
*/

$modalId = 'richTextEditorModal';
?>

<!-- Rich Text Editor Modal -->
<div class="modal fade rte-modal" id="<?= $modalId ?>" tabindex="-1" aria-labelledby="<?= $modalId ?>Label" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">

			<!-- Modal Header -->
			<div class="modal-header">
				<h5 class="modal-title" id="<?= $modalId ?>Label">
					<i class="bi bi-pencil-square"></i>
					<span id="rteModalTitle">Edit Text</span>
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>

			<!-- Modal Body -->
			<div class="modal-body" style="min-height: 650px;">
				
				<!-- Rich Text Editor Container -->
				<div id="richTextEditor" style="height: 600px;"></div>

			</div>

			<!-- Modal Footer -->
			<div class="modal-footer">
				<div class="btn-group-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
						<i class="bi bi-x-circle"></i> Cancel
					</button>
					<button type="button" class="btn btn-brand-primary" id="rteModalSave">
						<i class="bi bi-check-circle-fill"></i> Save Changes
					</button>
				</div>
			</div>

		</div>
	</div>
</div>

<script>
	/**
	* Rich Text Editor Modal Controller
	* Manages initialization, content loading, and saving
	*/
	(function() {
		'use strict';

		// Configuration
		const MODAL_ID = '<?= $modalId ?>';
		const EDITOR_ID = 'richTextEditor';

		// State
		let editorInstance = null;
		let currentFieldName = null;
		let currentFormElement = null;
		let isEditorInitialized = false;

	/**
	* Initialize the Rich Text Editor
	*/
		function initializeEditor()
		{
			// Only initialize once
			if (isEditorInitialized && editorInstance) {
				return;
			}

			// Clear any existing editor
			const editorElement = document.getElementById(EDITOR_ID);
			if (!editorElement) {
				console.error('Rich Text Editor: Container element not found');
				return;
			}

			// Destroy existing instance if it exists
			if (editorInstance) {
				try {
					editorInstance.destroy();
				} catch (e) {
					console.warn('Error destroying previous editor instance:', e);
				}
			}
			

			// Syncfusion RichTextEditor configuration
			editorInstance = new ej.richtexteditor.RichTextEditor({
				height: '600px',
				placeholder: 'Enter your content here...',
				toolbarSettings: {
					items: [
						'Bold', 'Italic', 'Underline', 'StrikeThrough', '|',
						'FontName', 'FontSize', 'FontColor', 'BackgroundColor', '|',
						'Formats', 'Alignments', '|',
						'OrderedList', 'UnorderedList', '|',
						'Indent', 'Outdent', '|',
						'CreateLink', '|',
						'ClearFormat', 'SourceCode', '|',
						'Undo', 'Redo'
					]
				},
				quickToolbarSettings: {
					enable: true,
					link: ['Open', 'Edit', 'UnLink']
				},
				created: function() {
					isEditorInitialized = true;
					console.log('RichTextEditor initialized successfully');
				}
			});

			editorInstance.appendTo('#' + EDITOR_ID);
		}

	/**
	* Find form field by name, searching through all forms on the page
	*/
		function findFormField(fieldName)
		{
			// Try to find field in any form on the page
			const field = document.querySelector(`[name="${fieldName}"]`);

			if (field) {
				// Get the parent form
				const form = field.closest('form');
				return { field, form };
			}

			return { field: null, form: null };
		}

	/**
	* Open the modal with content from a specific field
	*/
		function openEditor(fieldName, fieldLabel)
		{
			const { field, form } = findFormField(fieldName);

			if (!field) {
				console.error('Rich Text Editor: Field not found:', fieldName);
				alert('Error: Form field "' + fieldName + '" not found');
				return;
			}

			// Store current context
			currentFieldName = fieldName;
			currentFormElement = form;

			// Update modal title
			document.getElementById('rteModalTitle').textContent = fieldLabel || 'Edit Text';

			// Show modal first
			const modalElement = document.getElementById(MODAL_ID);
			const modal = new bootstrap.Modal(modalElement);
			modal.show();

			// Wait for modal to be fully shown, then initialize and load content
			modalElement.addEventListener('shown.bs.modal', function loadContent()
			{
				// Remove this specific listener after first execution
				modalElement.removeEventListener('shown.bs.modal', loadContent);

				// Initialize editor if needed
				if (!isEditorInitialized) {
					initializeEditor();
				}

				// Wait a bit for editor to be ready, then load content
				setTimeout(function() {
					if (editorInstance) {
						const content = field.value || '';
						editorInstance.value = content;

						// Focus the editor
						try {
							editorInstance.focusIn();
						} catch (e) {
							console.warn('Could not focus editor:', e);
						}
					}
				}, 100);
			});
		}

	/**
	* Save editor content back to the form field
	*/
		function saveContent()
		{
			if (!editorInstance || !currentFieldName) {
				console.error('Editor or field name not available');
				return;
			}

			const { field } = findFormField(currentFieldName);

			if (field) {
				// Get content from editor
				const content = editorInstance.value;

				// Update the form field
				field.value = content;

				// Trigger change event in case form has listeners
				const event = new Event('change', { bubbles: true });
				field.dispatchEvent(event);

				// Close modal
				const modal = bootstrap.Modal.getInstance(document.getElementById(MODAL_ID));
				if (modal) {
					modal.hide();
				}
			} else {
				console.error('Could not find field to save to:', currentFieldName);
			}
		}

	/**
	* Setup event listeners
	*/
		function setupEventListeners()
		{
			const modal = document.getElementById(MODAL_ID);

			if (!modal) {
				console.error('Rich Text Editor Modal not found');
				return;
			}

			// Clear state when modal is hidden
			modal.addEventListener('hidden.bs.modal', function() {
				currentFieldName = null;
				currentFormElement = null;
			});

			// Save button click handler
			const saveBtn = document.getElementById('rteModalSave');
			if (saveBtn) {
				saveBtn.addEventListener('click', saveContent);
			}

			// Setup trigger buttons (using event delegation)
			document.addEventListener('click', function(e) {
				const trigger = e.target.closest('[data-rte-trigger]');
				if (trigger) {
					e.preventDefault();
					e.stopPropagation();

					const fieldName = trigger.getAttribute('data-rte-field');
					const fieldLabel = trigger.getAttribute('data-rte-label') || 'Edit Text';

					if (fieldName) {
						openEditor(fieldName, fieldLabel);
					} else {
						console.error('Trigger button missing data-rte-field attribute');
					}
				}
			});
		}

		// Initialize when DOM is ready
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', setupEventListeners);
		} else {
			setupEventListeners();
		}

	})();
</script>