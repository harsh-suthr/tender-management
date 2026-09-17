/**
 * Tender Management System - Admin JavaScript
 *
 * Handles live financial calculations, dynamic form responsiveness,
 * and native WordPress Media Library integration for attachments.
 *
 * @package    Tender_PO_Management
 * @subpackage Tender_PO_Management/admin/js
 * @version    1.0.0
 */

(function($) {
	'use strict';

	var attachmentIndex = 0;
	var balanceManuallyEdited = false;

	/**
	 * Helper to format a numeric value into currency string.
	 *
	 * @param {number} amount
	 * @return {string}
	 */
	function formatCurrency(amount) {
		var symbol = (window.tpmAdmin && window.tpmAdmin.currencySymbol) ? window.tpmAdmin.currencySymbol : '₹';
		var isNegative = amount < 0;
		var formatted = Math.abs(amount).toLocaleString('en-US', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		});
		return (isNegative ? '-' : '') + symbol + formatted;
	}

	/**
	 * Recalculate Gross Profit, Net Profit, and Balance Due in real-time.
	 */
	function recalculateFinancials() {
		var purchase = parseFloat($('#purchase_price').val()) || 0;
		var sell = parseFloat($('#sell_price').val()) || 0;
		var expenses = parseFloat($('#expenses').val()) || 0;
		var payment = parseFloat($('#actual_payment').val()) || 0;

		var grossProfit = sell - purchase;
		var netProfit = grossProfit - expenses;
		var calculatedBalance = Math.max(0, sell - payment);

		// 1. Update Gross Profit displays
		var $grossEl = $('#calc-gross-profit');
		var $sideGrossEl = $('#sidebar-gross-profit');
		var grossText = formatCurrency(grossProfit);

		$grossEl.text(grossText);
		$sideGrossEl.text(grossText);

		if (grossProfit >= 0) {
			$grossEl.removeClass('tpm-metric-negative').addClass('tpm-metric-positive');
			$sideGrossEl.removeClass('tpm-metric-negative').addClass('tpm-metric-positive');
		} else {
			$grossEl.removeClass('tpm-metric-positive').addClass('tpm-metric-negative');
			$sideGrossEl.removeClass('tpm-metric-positive').addClass('tpm-metric-negative');
		}

		// 2. Update Net Profit displays
		var $netEl = $('#calc-net-profit');
		var $sideNetEl = $('#sidebar-net-profit');
		var netText = formatCurrency(netProfit);

		$netEl.text(netText);
		$sideNetEl.text(netText);

		if (netProfit >= 0) {
			$netEl.removeClass('tpm-metric-negative').addClass('tpm-metric-positive');
			$sideNetEl.removeClass('tpm-metric-negative').addClass('tpm-metric-positive');
		} else {
			$netEl.removeClass('tpm-metric-positive').addClass('tpm-metric-negative');
			$sideNetEl.removeClass('tpm-metric-positive').addClass('tpm-metric-negative');
		}

		// 3. Update Balance Due display
		var $balanceEl = $('#calc-balance-due');
		$balanceEl.text(formatCurrency(calculatedBalance));

		// Auto-populate Balance field ONLY if user hasn't explicitly customized it
		if (!balanceManuallyEdited && ($('#balance_payment_due').val() === '' || $('#balance_payment_due').val() === '0.00' || $('#balance_payment_due').val() === '0')) {
			if (sell > 0) {
				$('#balance_payment_due').val(calculatedBalance.toFixed(2));
			}
		}
	}

	/**
	 * Detect appropriate file icon based on mime type or file extension.
	 *
	 * @param {string} mime
	 * @param {string} filename
	 * @return {string} Dashicon class name
	 */
	function getFileIcon(mime, filename) {
		var ext = filename.split('.').pop().toLowerCase();

		if (mime.indexOf('pdf') !== -1 || ext === 'pdf') {
			return 'dashicons-media-document';
		}
		if (mime.indexOf('sheet') !== -1 || mime.indexOf('excel') !== -1 || ext === 'xls' || ext === 'xlsx') {
			return 'dashicons-media-spreadsheet';
		}
		if (mime.indexOf('word') !== -1 || ext === 'doc' || ext === 'docx') {
			return 'dashicons-media-document';
		}
		if (mime.indexOf('image') !== -1 || ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'webp') {
			return 'dashicons-format-image';
		}
		return 'dashicons-media-default';
	}

	/**
	 * Format file size in human-readable bytes.
	 *
	 * @param {number} bytes
	 * @return {string}
	 */
	function formatBytes(bytes) {
		if (!bytes || bytes <= 0) {
			return '';
		}
		var k = 1024;
		var sizes = ['Bytes', 'KB', 'MB', 'GB'];
		var i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
	}

	$(document).ready(function() {

		// 1. Generic confirmation trigger for destructive operations
		$(document).on('click', '[data-tpm-confirm]', function(e) {
			var message = $(this).data('tpm-confirm') || (window.tpmAdmin && window.tpmAdmin.strings ? window.tpmAdmin.strings.confirmDelete : 'Are you sure you want to proceed?');
			if (!window.confirm(message)) {
				e.preventDefault();
				return false;
			}
		});

		// 2. Financial calculation live triggers
		$(document).on('input change', '.tpm-calc-trigger', function() {
			recalculateFinancials();
		});

		// Track manual edits to Balance Payment Due so we don't blindly clobber it
		$('#balance_payment_due').on('input change', function() {
			balanceManuallyEdited = true;
		});

		// "Auto-fill Balance" button trigger
		$('#tpm-btn-autofill-balance').on('click', function(e) {
			e.preventDefault();
			var sell = parseFloat($('#sell_price').val()) || 0;
			var payment = parseFloat($('#actual_payment').val()) || 0;
			var calculated = Math.max(0, sell - payment);
			$('#balance_payment_due').val(calculated.toFixed(2));
			balanceManuallyEdited = false;
			recalculateFinancials();
		});

		// Run calculation once on page load to initialize summary badges
		if ($('#purchase_price').length) {
			recalculateFinancials();
		}

		// 3. Record Type selection cards interaction
		$('input[name="record_type"]').on('change', function() {
			var selectedType = $(this).val();
			$('.tpm-radio-card').removeClass('tpm-radio-selected');
			$(this).closest('.tpm-radio-card').addClass('tpm-radio-selected');

			if (selectedType === 'po') {
				$('#sidebar-record-type-badge strong').text('Purchase Order');
				$('#tender-number-label').text('PO Reference / Tracking #');
				$('#tender_number').attr('placeholder', 'e.g. PO-2026-001');
			} else {
				$('#sidebar-record-type-badge strong').text('Tender');
				$('#tender-number-label').text('Tender Number');
				$('#tender_number').attr('placeholder', 'e.g. TND-2026-001');
			}
		});

		// 4. Status selection synchronization with sidebar badge
		$('#status').on('change', function() {
			var text = $(this).find('option:selected').text().trim();
			$('#sidebar-status-badge').text(text);
		});

		// 5. WordPress Media Library Integration for Attachments
		var mediaFrame;
		var labels = (window.tpmAdmin && window.tpmAdmin.attachmentLabels) ? window.tpmAdmin.attachmentLabels : {
			'Tender Document': 'Tender Document',
			'Customer PO': 'Customer PO',
			'Quotation': 'Quotation',
			'Invoice': 'Invoice',
			'Shipping Document': 'Shipping Document',
			'Other': 'Other'
		};

		$('#tpm-add-attachments-btn').on('click', function(e) {
			e.preventDefault();

			// If frame exists, open it
			if (mediaFrame) {
				mediaFrame.open();
				return;
			}

			// Create media frame
			mediaFrame = wp.media({
				title: (window.tpmAdmin && window.tpmAdmin.strings && window.tpmAdmin.strings.chooseFile) ? window.tpmAdmin.strings.chooseFile : 'Select or Upload Documents',
				button: {
					text: (window.tpmAdmin && window.tpmAdmin.strings && window.tpmAdmin.strings.selectFilesBtn) ? window.tpmAdmin.strings.selectFilesBtn : 'Attach Selected Files'
				},
				multiple: true
			});

			// When items are selected from media modal
			mediaFrame.on('select', function() {
				var selection = mediaFrame.state().get('selection');

				selection.map(function(attachment) {
					attachment = attachment.toJSON();

					var attId = attachment.id;

					// Check if already added
					if ($('#tpm-attachments-list input[value="' + attId + '"]').length > 0) {
						return;
					}

					$('#tpm-no-attachments-placeholder').hide();

					var fileName = attachment.filename || attachment.title || 'Document';
					var fileType = attachment.mime || 'application/octet-stream';
					var fileSize = attachment.filesizeInBytes || attachment.filesize || 0;
					var iconClass = getFileIcon(fileType, fileName);
					var sizeFormatted = formatBytes(fileSize);

					// Determine a sensible default label
					var defaultLabel = 'Other';
					var ext = fileName.split('.').pop().toLowerCase();
					if (ext === 'pdf') {
						defaultLabel = ( $('input[name="record_type"]:checked').val() === 'po' ) ? 'Customer PO' : 'Tender Document';
					}

					// Build options HTML for label dropdown
					var labelOptionsHtml = '';
					$.each(labels, function(key, label) {
						var isSel = (key === defaultLabel) ? 'selected' : '';
						labelOptionsHtml += '<option value="' + key + '" ' + isSel + '>' + label + '</option>';
					});

					var rowHtml = '' +
						'<div class="tpm-attachment-row" id="tpm-att-row-' + attId + '">' +
							'<input type="hidden" name="attachments[' + attachmentIndex + '][attachment_id]" value="' + attId + '">' +
							'<input type="hidden" name="attachments[' + attachmentIndex + '][file_name]" value="' + $('<div>').text(fileName).html() + '">' +
							'<input type="hidden" name="attachments[' + attachmentIndex + '][file_type]" value="' + $('<div>').text(fileType).html() + '">' +
							'<input type="hidden" name="attachments[' + attachmentIndex + '][file_size]" value="' + fileSize + '">' +
							'<div class="tpm-att-icon"><span class="dashicons ' + iconClass + '"></span></div>' +
							'<div class="tpm-att-details">' +
								'<div class="tpm-att-name">' + $('<div>').text(fileName).html() + '</div>' +
								'<div class="tpm-att-meta">' + fileType + (sizeFormatted ? ' &bull; ' + sizeFormatted : '') + '</div>' +
							'</div>' +
							'<div class="tpm-att-label-select">' +
								'<label class="screen-reader-text">Attachment Label</label>' +
								'<select name="attachments[' + attachmentIndex + '][attachment_label]" class="tpm-select tpm-select-sm">' +
									labelOptionsHtml +
								'</select>' +
							'</div>' +
							'<div class="tpm-att-remove">' +
								'<button type="button" class="tpm-btn-icon-danger tpm-remove-att-btn" title="Remove" data-target="#tpm-att-row-' + attId + '">' +
									'<span class="dashicons dashicons-trash"></span>' +
								'</button>' +
							'</div>' +
						'</div>';

					$('#tpm-attachments-list').append(rowHtml);
					attachmentIndex++;
				});
			});

			mediaFrame.open();
		});

		// Remove newly added staged attachment row
		$(document).on('click', '.tpm-remove-att-btn', function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			$(target).remove();

			if ($('#tpm-attachments-list .tpm-attachment-row').length === 0) {
				$('#tpm-no-attachments-placeholder').show();
			}
		});

		// Remove existing attachment relationship in Edit Record form
		$(document).on('click', '.tpm-remove-existing-att-btn', function(e) {
			e.preventDefault();
			var attId = $(this).data('id');
			var $row  = $(this).closest('.tpm-existing-att-row');
			var confirmMsg = (window.tpmAdmin && window.tpmAdmin.strings && window.tpmAdmin.strings.confirmRemoveAtt)
				? window.tpmAdmin.strings.confirmRemoveAtt
				: 'Remove this document attachment relationship? The file will remain safely in the WordPress Media Library.';

			if (window.confirm(confirmMsg)) {
				// Queue attachment ID for relationship deletion upon form save
				if ($('#tpm-record-form input[name="deleted_attachments[]"][value="' + attId + '"]').length === 0) {
					$('#tpm-record-form').append('<input type="hidden" name="deleted_attachments[]" value="' + attId + '">');
				}

				$row.fadeOut(200, function() {
					$(this).remove();
					if ($('#tpm-existing-attachments-list .tpm-existing-att-row').length === 0) {
						$('#tpm-no-existing-attachments-msg').show();
					}
				});
			}
		});

		// 6. Record Details Tab Navigation
		function activateTab(tabId) {
			if (!tabId) {
				return;
			}
			tabId = tabId.replace(/^#/, '');

			var $targetLink = $('#tpm-record-tabs a[data-tab="' + tabId + '"]');
			var $targetPane = $('#tab-pane-' + tabId);

			if ($targetLink.length && $targetPane.length) {
				$('#tpm-record-tabs a').removeClass('nav-tab-active');
				$targetLink.addClass('nav-tab-active');

				$('.tpm-tab-pane').removeClass('active');
				$targetPane.addClass('active');

				if (history.replaceState) {
					history.replaceState(null, null, '#' + tabId);
				}
			}
		}

		$(document).on('click', '.tpm-tab-link, .tpm-tab-trigger', function(e) {
			e.preventDefault();
			var tabId = $(this).data('tab') || $(this).attr('href');
			activateTab(tabId);
		});

		// Activate tab if URL hash is present on load
		if ($('#tpm-record-tabs').length) {
			var initialHash = window.location.hash;
			if (initialHash) {
				activateTab(initialHash);
			}
		}

		// 7. PDF & Document In-Admin Preview Modal
		function closePreviewModal() {
			$('#tpm-preview-modal').fadeOut(150, function() {
				// Clear iframe and image src to terminate background rendering and free memory
				$('#tpm-pdf-iframe').attr('src', '');
				$('#tpm-preview-img').attr('src', '');
				$('#tpm-modal-pdf-container').hide();
				$('#tpm-modal-img-container').hide();
			});
			$('body').removeClass('tpm-modal-open');
		}

		// Click to Preview PDF
		$(document).on('click', '.tpm-preview-pdf-btn', function(e) {
			e.preventDefault();
			var pdfUrl = $(this).data('pdf-url');
			var pdfTitle = $(this).data('pdf-title') || 'PDF Document Preview';

			if (!pdfUrl) {
				return;
			}

			$('#tpm-preview-modal-title').text(pdfTitle);
			$('#tpm-modal-external-link').attr('href', pdfUrl);
			$('#tpm-modal-img-container').hide();
			$('#tpm-modal-pdf-container').show();
			$('#tpm-pdf-iframe').attr('src', pdfUrl);

			$('#tpm-preview-modal').fadeIn(150);
			$('body').addClass('tpm-modal-open');
		});

		// Click to Preview Image
		$(document).on('click', '.tpm-preview-img-btn', function(e) {
			e.preventDefault();
			var imgUrl = $(this).data('img-url');
			var imgTitle = $(this).data('img-title') || 'Image Preview';

			if (!imgUrl) {
				return;
			}

			$('#tpm-preview-modal-title').text(imgTitle);
			$('#tpm-modal-external-link').attr('href', imgUrl);
			$('#tpm-modal-pdf-container').hide();
			$('#tpm-modal-img-container').show();
			$('#tpm-preview-img').attr('src', imgUrl);

			$('#tpm-preview-modal').fadeIn(150);
			$('body').addClass('tpm-modal-open');
		});

		// Close modal interactions
		$(document).on('click', '#tpm-modal-close-btn, #tpm-modal-backdrop', function(e) {
			e.preventDefault();
			closePreviewModal();
		});

		// Close modal on Escape key press
		$(document).on('keydown', function(e) {
			if (e.which === 27) {
				if ($('#tpm-preview-modal').is(':visible')) {
					closePreviewModal();
				}
				if ($('#tpm-edit-payment-modal').is(':visible')) {
					$('#tpm-edit-payment-modal').fadeOut(150);
					$('body').removeClass('tpm-modal-open');
				}
			}
		});

		// 8. Record Details "+ Upload Attachment" (WordPress Media Library)
		var detailsMediaFrame;
		var detailsStagedIndex = 0;

		$(document).on('click', '#tpm-details-upload-btn, #tpm-details-upload-empty-btn', function(e) {
			e.preventDefault();

			if (detailsMediaFrame) {
				detailsMediaFrame.open();
				return;
			}

			detailsMediaFrame = wp.media({
				title: (window.tpmAdmin && window.tpmAdmin.strings && window.tpmAdmin.strings.chooseFile) ? window.tpmAdmin.strings.chooseFile : 'Select or Upload Documents',
				button: {
					text: (window.tpmAdmin && window.tpmAdmin.strings && window.tpmAdmin.strings.selectFilesBtn) ? window.tpmAdmin.strings.selectFilesBtn : 'Attach Selected Files'
				},
				multiple: true
			});

			detailsMediaFrame.on('select', function() {
				var selection = detailsMediaFrame.state().get('selection');

				selection.map(function(attachment) {
					attachment = attachment.toJSON();
					var attId = attachment.id;

					// Prevent duplicate staging
					if ($('#tpm-details-staged-list input[value="' + attId + '"]').length > 0) {
						return;
					}

					var fileName = attachment.filename || attachment.title || 'Document';
					var fileType = attachment.mime || 'application/octet-stream';
					var fileSize = attachment.filesizeInBytes || attachment.filesize || 0;
					var iconClass = getFileIcon(fileType, fileName);
					var sizeFormatted = formatBytes(fileSize);

					// Determine sensible default label
					var defaultLabel = 'Other';
					var ext = fileName.split('.').pop().toLowerCase();
					if (ext === 'pdf') {
						defaultLabel = 'Tender Document';
					}

					var labelOptionsHtml = '';
					$.each(labels, function(key, label) {
						var isSel = (key === defaultLabel) ? 'selected' : '';
						labelOptionsHtml += '<option value="' + key + '" ' + isSel + '>' + label + '</option>';
					});

					var stagedHtml = '' +
						'<div class="tpm-staged-item" id="tpm-staged-item-' + attId + '">' +
							'<input type="hidden" name="attachments[' + detailsStagedIndex + '][attachment_id]" value="' + attId + '">' +
							'<input type="hidden" name="attachments[' + detailsStagedIndex + '][file_name]" value="' + $('<div>').text(fileName).html() + '">' +
							'<input type="hidden" name="attachments[' + detailsStagedIndex + '][file_type]" value="' + $('<div>').text(fileType).html() + '">' +
							'<input type="hidden" name="attachments[' + detailsStagedIndex + '][file_size]" value="' + fileSize + '">' +
							'<div class="tpm-staged-item-info">' +
								'<span class="dashicons ' + iconClass + ' tpm-staged-icon"></span>' +
								'<div class="tpm-staged-texts">' +
									'<strong class="tpm-staged-name">' + $('<div>').text(fileName).html() + '</strong>' +
									'<span class="tpm-staged-meta">' + fileType + (sizeFormatted ? ' &bull; ' + sizeFormatted : '') + '</span>' +
								'</div>' +
							'</div>' +
							'<div class="tpm-staged-item-label">' +
								'<label class="screen-reader-text">Attachment Label</label>' +
								'<select name="attachments[' + detailsStagedIndex + '][attachment_label]" class="tpm-select tpm-select-sm">' +
									labelOptionsHtml +
								'</select>' +
							'</div>' +
							'<div class="tpm-staged-item-remove">' +
								'<button type="button" class="tpm-btn-icon-danger tpm-remove-staged-btn" data-target="#tpm-staged-item-' + attId + '" title="Remove from list">' +
									'<span class="dashicons dashicons-trash"></span>' +
								'</button>' +
							'</div>' +
						'</div>';

					$('#tpm-details-staged-list').append(stagedHtml);
					detailsStagedIndex++;
				});

				if ($('#tpm-details-staged-list .tpm-staged-item').length > 0) {
					$('#tpm-details-staging-box').slideDown(200);
				}
			});

			detailsMediaFrame.open();
		});

		// Cancel staging
		$(document).on('click', '#tpm-details-cancel-upload-btn', function(e) {
			e.preventDefault();
			$('#tpm-details-staged-list').empty();
			$('#tpm-details-staging-box').slideUp(200);
		});

		// Remove single staged item
		$(document).on('click', '.tpm-remove-staged-btn', function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			$(target).remove();

			if ($('#tpm-details-staged-list .tpm-staged-item').length === 0) {
				$('#tpm-details-staging-box').slideUp(200);
			}
		});

		// 9. WP_List_Table Bulk Action submission routing
		$(document).on('click', '#doaction, #doaction2', function(e) {
			var $btn = $(this);
			var $form = $btn.closest('form');
			var actionSelect = $btn.prev('select');
			var selectedAction = actionSelect.val();

			if (!selectedAction || selectedAction === '-1') {
				e.preventDefault();
				return false;
			}

			// Verify if any items are selected
			var checkedCount = $form.find('input[name="record_ids[]"]:checked').length;
			if (checkedCount === 0) {
				alert((window.tpmAdmin && window.tpmAdmin.strings && window.tpmAdmin.strings.selectRecords) ? window.tpmAdmin.strings.selectRecords : 'Please select at least one record to perform this action.');
				e.preventDefault();
				return false;
			}

			// If deleting, prompt user confirmation
			if (selectedAction === 'delete') {
				var confirmMsg = (window.tpmAdmin && window.tpmAdmin.strings && window.tpmAdmin.strings.confirmBulkDelete) ? window.tpmAdmin.strings.confirmBulkDelete : 'Are you sure you want to permanently delete the selected records? This action cannot be undone.';
				if (!window.confirm(confirmMsg)) {
					e.preventDefault();
					return false;
				}
			}

			// Configure form for POST to admin-post.php
			$form.attr('method', 'post');
			$form.attr('action', (window.tpmAdmin && window.tpmAdmin.adminPostUrl) ? window.tpmAdmin.adminPostUrl : 'admin-post.php');

			if ($form.find('input[name="action"][value="tpm_bulk_records"]').length === 0) {
				$form.append('<input type="hidden" name="action" value="tpm_bulk_records">');
			}
		});

		// 10. Payment Management: Edit Payment Modal Dialog
		$(document).on('click', '.tpm-btn-edit-payment', function(e) {
			e.preventDefault();
			var $btn = $(this);
			$('#tpm-edit-payment-id').val($btn.data('id'));
			$('#edit_payment_amount').val($btn.data('amount'));
			$('#edit_payment_date').val($btn.data('date'));
			$('#edit_payment_method').val($btn.data('method'));
			$('#edit_reference_number').val($btn.data('reference'));
			$('#edit_payment_notes').val($btn.data('notes'));
			$('#tpm-edit-payment-nonce').val($btn.data('nonce'));

			$('#tpm-edit-payment-modal').fadeIn(150);
			$('body').addClass('tpm-modal-open');
		});

		$(document).on('click', '#tpm-edit-payment-close-btn, #tpm-edit-payment-cancel-btn, #tpm-edit-payment-backdrop', function(e) {
			e.preventDefault();
			$('#tpm-edit-payment-modal').fadeOut(150);
			$('body').removeClass('tpm-modal-open');
		});

	});

})(jQuery);

