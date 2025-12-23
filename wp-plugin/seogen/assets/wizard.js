/**
 * Wizard JavaScript
 * 
 * Handles wizard interactivity, validation, and step navigation.
 */

(function($) {
	'use strict';
	
	var SeogenWizard = {
		currentStep: 1,
		
		init: function() {
			this.bindEvents();
			this.loadCurrentStep();
		},
		
		bindEvents: function() {
			var self = this;
			
			// Form submissions
			$('#seogen-wizard-settings-form').on('submit', function(e) {
				e.preventDefault();
				self.handleSettingsSubmit($(this));
			});
			
			$('#seogen-wizard-business-form').on('submit', function(e) {
				e.preventDefault();
				self.handleBusinessSubmit($(this));
			});
			
			// Navigation buttons
			$('.seogen-wizard-back').on('click', function(e) {
				e.preventDefault();
				self.goToPreviousStep();
			});
			
			$('.seogen-wizard-next').on('click', function(e) {
				e.preventDefault();
				var step = $(this).data('step');
				self.validateAndAdvance(step);
			});
			
			// Refresh buttons
			$('.seogen-wizard-refresh').on('click', function(e) {
				e.preventDefault();
				var type = $(this).data('refresh');
				self.refreshList(type);
			});
			
			// Add/Delete service buttons
			$('.seogen-wizard-add-service').on('click', function(e) {
				e.preventDefault();
				self.addService();
			});
			
			$('.seogen-wizard-bulk-add-services').on('click', function(e) {
				e.preventDefault();
				self.bulkAddServices();
			});
			
			$(document).on('click', '.seogen-wizard-delete-service', function(e) {
				e.preventDefault();
				var index = $(this).data('index');
				var name = $(this).data('name');
				self.deleteService(index, name);
			});
			
			// Add/Delete city buttons
			$('.seogen-wizard-add-city').on('click', function(e) {
				e.preventDefault();
				self.addCity();
			});
			
			$(document).on('click', '.seogen-wizard-delete-city', function(e) {
				e.preventDefault();
				var index = $(this).data('index');
				var name = $(this).data('name');
				self.deleteCity(index, name);
			});
			
			// Enter key support for add forms
			$('#seogen-wizard-new-service').on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					self.addService();
				}
			});
			
			$('#seogen-wizard-new-city').on('keypress', function(e) {
				if (e.which === 13) {
					e.preventDefault();
					self.addCity();
				}
			});
			
			// Generation buttons
			$('.seogen-wizard-start-generation').on('click', function(e) {
				e.preventDefault();
				self.startGeneration();
			});
			
			$('.seogen-wizard-skip-generation').on('click', function(e) {
				e.preventDefault();
				self.skipGeneration();
			});
		},
		
		loadCurrentStep: function() {
			var stepAttr = $('.seogen-wizard-step-content:visible').data('step');
			if (stepAttr) {
				this.currentStep = parseInt(stepAttr);
			}
		},
		
		showStep: function(step) {
			$('.seogen-wizard-step-content').hide();
			$('.seogen-wizard-step-content[data-step="' + step + '"]').show();
			this.currentStep = step;
			this.updateProgressBar();
		},
		
		updateProgressBar: function() {
			var progress = ((this.currentStep - 1) / 4) * 100;
			$('.seogen-wizard-progress-fill').css('width', progress + '%');
		},
		
		goToPreviousStep: function() {
			if (this.currentStep > 1) {
				this.showStep(this.currentStep - 1);
			}
		},
		
		handleSettingsSubmit: function($form) {
			var self = this;
			var $button = $form.find('button[type="submit"]');
			var originalText = $button.text();
			
			// Show loading state
			$button.addClass('loading').prop('disabled', true);
			this.hideValidationMessage($form);
			
			// Submit form via AJAX
			$.ajax({
				url: $form.attr('action'),
				method: 'POST',
				data: $form.serialize(),
				success: function(response) {
					// Validate the step
					self.validateAndAdvance(1);
				},
				error: function(xhr) {
					$button.removeClass('loading').prop('disabled', false);
					self.showValidationMessage($form, 'error', 'Failed to save settings. Please try again.');
				}
			});
		},
		
		handleBusinessSubmit: function($form) {
			var self = this;
			var $button = $form.find('button[type="submit"]');
			var originalText = $button.text();
			
			// Show loading state
			$button.addClass('loading').prop('disabled', true);
			this.hideValidationMessage($form);
			
			// Submit form via AJAX
			$.ajax({
				url: $form.attr('action'),
				method: 'POST',
				data: $form.serialize(),
				success: function(response) {
					// Validate the step
					self.validateAndAdvance(2);
				},
				error: function(xhr) {
					$button.removeClass('loading').prop('disabled', false);
					self.showValidationMessage($form, 'error', 'Failed to save business config. Please try again.');
				}
			});
		},
		
		validateAndAdvance: function(step) {
			var self = this;
			
			// Show loading state
			var $button = $('.seogen-wizard-step-content[data-step="' + step + '"] .button-primary');
			$button.addClass('loading').prop('disabled', true);
			
			// Validate via AJAX
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_validate_step',
					nonce: seogenWizard.nonce,
					step: step
				},
				success: function(response) {
					if (response.success) {
						// Advance to next step
						self.advanceStep(step);
					} else {
						$button.removeClass('loading').prop('disabled', false);
						var message = response.data && response.data.message ? response.data.message : 'Validation failed';
						self.showValidationMessage(
							$('.seogen-wizard-step-content[data-step="' + step + '"]'),
							'error',
							message
						);
					}
				},
				error: function(xhr) {
					$button.removeClass('loading').prop('disabled', false);
					self.showValidationMessage(
						$('.seogen-wizard-step-content[data-step="' + step + '"]'),
						'error',
						'An error occurred. Please try again.'
					);
				}
			});
		},
		
		advanceStep: function(currentStep) {
			var self = this;
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_advance_step',
					nonce: seogenWizard.nonce,
					step: currentStep
				},
				success: function(response) {
					if (response.success) {
						// Mark step as completed
						$('.seogen-wizard-step').eq(currentStep - 1).addClass('completed');
						
						// Show success message briefly
						self.showValidationMessage(
							$('.seogen-wizard-step-content[data-step="' + currentStep + '"]'),
							'success',
							response.data.message || 'Step completed!'
						);
						
						// Advance to next step after brief delay
						setTimeout(function() {
							self.showStep(currentStep + 1);
						}, 500);
					} else {
						var $button = $('.seogen-wizard-step-content[data-step="' + currentStep + '"] .button-primary');
						$button.removeClass('loading').prop('disabled', false);
						self.showValidationMessage(
							$('.seogen-wizard-step-content[data-step="' + currentStep + '"]'),
							'error',
							response.data.message || 'Failed to advance step'
						);
					}
				},
				error: function(xhr) {
					var $button = $('.seogen-wizard-step-content[data-step="' + currentStep + '"] .button-primary');
					$button.removeClass('loading').prop('disabled', false);
					self.showValidationMessage(
						$('.seogen-wizard-step-content[data-step="' + currentStep + '"]'),
						'error',
						'An error occurred. Please try again.'
					);
				}
			});
		},
		
		addService: function() {
			var self = this;
			var $input = $('#seogen-wizard-new-service');
			var $hubSelect = $('#seogen-wizard-service-hub');
			var serviceName = $input.val().trim();
			var serviceHub = $hubSelect.val();
			
			if (!serviceName) {
				alert('Please enter a service name');
				return;
			}
			
			if (!serviceHub) {
				alert('Please select a hub category');
				return;
			}
			
			var $button = $('.seogen-wizard-add-service');
			$button.addClass('loading').prop('disabled', true);
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_add_service',
					nonce: seogenWizard.nonce,
					service_name: serviceName,
					service_hub: serviceHub
				},
				success: function(response) {
					if (response.success) {
						$input.val('');
						$hubSelect.val('');
						location.reload();
					} else {
						alert(response.data.message || 'Failed to add service');
						$button.removeClass('loading').prop('disabled', false);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$button.removeClass('loading').prop('disabled', false);
				}
			});
		},
		
		bulkAddServices: function() {
			var self = this;
			var $textarea = $('#seogen-wizard-bulk-services');
			var bulkText = $textarea.val().trim();
			
			if (!bulkText) {
				alert('Please enter services to add');
				return;
			}
			
			var $button = $('.seogen-wizard-bulk-add-services');
			$button.addClass('loading').prop('disabled', true);
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_bulk_add_services',
					nonce: seogenWizard.nonce,
					bulk_text: bulkText
				},
				success: function(response) {
					if (response.success) {
						$textarea.val('');
						alert(response.data.message || 'Services added successfully');
						location.reload();
					} else {
						alert(response.data.message || 'Failed to add services');
						$button.removeClass('loading').prop('disabled', false);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$button.removeClass('loading').prop('disabled', false);
				}
			});
		},
		
		deleteService: function(index, name) {
			var self = this;
			
			if (!confirm('Delete "' + name + '"?')) {
				return;
			}
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_delete_service',
					nonce: seogenWizard.nonce,
					index: index
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to delete service');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				}
			});
		},
		
		addCity: function() {
			var self = this;
			var $input = $('#seogen-wizard-new-city');
			var cityName = $input.val().trim();
			
			if (!cityName) {
				alert('Please enter a city name');
				return;
			}
			
			// Validate format: City, State
			var parts = cityName.split(',').map(function(part) { return part.trim(); });
			if (parts.length !== 2 || !parts[0] || !parts[1]) {
				alert('Please enter city in format: City Name, State (e.g., "Tulsa, OK")');
				return;
			}
			
			// Validate state is 2 characters
			if (parts[1].length !== 2) {
				alert('Please use 2-letter state abbreviation (e.g., "OK", "TX", "NY")');
				return;
			}
			
			var $button = $('.seogen-wizard-add-city');
			$button.addClass('loading').prop('disabled', true);
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_add_city',
					nonce: seogenWizard.nonce,
					city_name: cityName
				},
				success: function(response) {
					if (response.success) {
						$input.val('');
						location.reload();
					} else {
						alert(response.data.message || 'Failed to add city');
						$button.removeClass('loading').prop('disabled', false);
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
					$button.removeClass('loading').prop('disabled', false);
				}
			});
		},
		
		deleteCity: function(index, name) {
			var self = this;
			
			if (!confirm('Delete "' + name + '"?')) {
				return;
			}
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_delete_city',
					nonce: seogenWizard.nonce,
					index: index
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to delete city');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				}
			});
		},
		
		refreshList: function(type) {
			var self = this;
			var $container = $('#seogen-wizard-' + type + '-container');
			var $button = $('.seogen-wizard-refresh[data-refresh="' + type + '"]');
			
			$button.addClass('loading').prop('disabled', true);
			
			// Reload the page to refresh the list
			// In a future enhancement, this could be done via AJAX
			location.reload();
		},
		
		startGeneration: function() {
			var self = this;
			var $button = $('.seogen-wizard-start-generation');
			
			$button.addClass('loading').prop('disabled', true);
			$('.seogen-wizard-skip-generation').prop('disabled', true);
			
			// Show progress container
			$('.seogen-wizard-generation-progress').show();
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_start_generation',
					nonce: seogenWizard.nonce
				},
				success: function(response) {
					if (response.success) {
						self.jobId = response.data.job_id;
						self.totalPages = response.data.total;
						
						// Update initial progress
						self.updateProgress(0, self.totalPages, 0, 0);
						
						// Start processing batches
						self.processBatch();
					} else {
						$button.removeClass('loading').prop('disabled', false);
						$('.seogen-wizard-skip-generation').prop('disabled', false);
						alert(response.data.message || 'Failed to start generation');
					}
				},
				error: function(xhr) {
					$button.removeClass('loading').prop('disabled', false);
					$('.seogen-wizard-skip-generation').prop('disabled', false);
					alert('An error occurred. Please try again.');
				}
			});
		},
		
		processBatch: function() {
			var self = this;
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_process_batch',
					nonce: seogenWizard.nonce,
					job_id: self.jobId
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;
						
						// Update progress
						self.updateProgress(data.processed, data.total, data.successful, data.failed);
						
						// Show batch results
						if (data.batch_results && data.batch_results.length > 0) {
							self.showBatchResults(data.batch_results);
						}
						
						// Check if complete
						if (data.complete) {
							self.onGenerationComplete(data);
						} else {
							// Process next batch
							setTimeout(function() {
								self.processBatch();
							}, 500);
						}
					} else {
						alert(response.data.message || 'Generation failed');
						$('.seogen-wizard-start-generation').removeClass('loading').prop('disabled', false);
						$('.seogen-wizard-skip-generation').prop('disabled', false);
					}
				},
				error: function(xhr) {
					alert('An error occurred during generation. Please try again.');
					$('.seogen-wizard-start-generation').removeClass('loading').prop('disabled', false);
					$('.seogen-wizard-skip-generation').prop('disabled', false);
				}
			});
		},
		
		updateProgress: function(processed, total, successful, failed) {
			var percentage = total > 0 ? Math.round((processed / total) * 100) : 0;
			
			$('.seogen-wizard-progress-bar').css('width', percentage + '%');
			$('.seogen-wizard-progress-text').text(processed + ' / ' + total + ' pages');
			$('.seogen-wizard-progress-percentage').text(percentage + '%');
			
			if (successful > 0 || failed > 0) {
				$('.seogen-wizard-progress-stats').html(
					'<span style="color: #46b450;">✓ ' + successful + ' successful</span> ' +
					(failed > 0 ? '<span style="color: #dc3232;">✗ ' + failed + ' failed</span>' : '')
				);
			}
		},
		
		showBatchResults: function(results) {
			var $log = $('.seogen-wizard-generation-log');
			
			results.forEach(function(result) {
				var icon = result.success ? '✓' : '✗';
				var color = result.success ? '#46b450' : '#dc3232';
				var text = result.service + ' in ' + result.city;
				
				if (!result.success && result.error) {
					text += ' - ' + result.error;
				}
				
				$log.prepend(
					'<div style="color: ' + color + '; padding: 4px 0;">' +
					icon + ' ' + text +
					'</div>'
				);
			});
			
			// Keep log scrolled to top to show latest
			$log.scrollTop(0);
		},
		
		pollGenerationProgress: function() {
			var self = this;
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_generation_progress',
					nonce: seogenWizard.nonce
				},
				success: function(response) {
					if (response.success && response.data.generation) {
						var generation = response.data.generation;
						
						// Update progress display
						self.updateGenerationProgress(generation);
						
						// Check if all complete
						if (generation.service_hubs === 'completed' &&
						    generation.service_pages === 'completed' &&
						    generation.city_hubs === 'completed') {
							// Generation complete
							self.onGenerationComplete();
						} else {
							// Continue polling
							setTimeout(function() {
								self.pollGenerationProgress();
							}, 3000);
						}
					}
				},
				error: function(xhr) {
					// Continue polling on error
					setTimeout(function() {
						self.pollGenerationProgress();
					}, 5000);
				}
			});
		},
		
		updateGenerationProgress: function(generation) {
			var html = '<ul>';
			
			if (generation.service_hubs === 'completed') {
				html += '<li>✓ Service Hub Pages - Completed</li>';
			} else if (generation.service_hubs === 'running') {
				html += '<li>⟳ Service Hub Pages - In Progress...</li>';
			} else {
				html += '<li>□ Service Hub Pages - Pending</li>';
			}
			
			if (generation.service_pages === 'completed') {
				html += '<li>✓ Service + City Pages - Completed</li>';
			} else if (generation.service_pages === 'running') {
				html += '<li>⟳ Service + City Pages - In Progress...</li>';
			} else {
				html += '<li>□ Service + City Pages - Pending</li>';
			}
			
			if (generation.city_hubs === 'completed') {
				html += '<li>✓ City Hub Pages - Completed</li>';
			} else if (generation.city_hubs === 'running') {
				html += '<li>⟳ City Hub Pages - In Progress...</li>';
			} else {
				html += '<li>□ City Hub Pages - Pending</li>';
			}
			
			html += '</ul>';
			
			$('.seogen-wizard-progress-details').html(html);
		},
		
		onGenerationComplete: function(data) {
			var self = this;
			
			$('.seogen-wizard-start-generation').removeClass('loading').prop('disabled', false);
			$('.seogen-wizard-skip-generation').prop('disabled', false);
			
			// Show completion message
			var message = 'Generation complete!\n\n';
			message += 'Successfully generated: ' + data.successful + ' pages\n';
			if (data.failed > 0) {
				message += 'Failed: ' + data.failed + ' pages\n';
			}
			message += '\nRedirecting to your pages...';
			
			alert(message);
			
			// Redirect to service pages
			window.location.href = seogenWizard.adminurl + 'edit.php?post_type=service_page';
		},
		
		skipGeneration: function() {
			var self = this;
			
			if (!confirm('Are you sure you want to skip automated generation? You can generate pages manually later.')) {
				return;
			}
			
			var $button = $('.seogen-wizard-skip-generation');
			$button.addClass('loading').prop('disabled', true);
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_skip_generation',
					nonce: seogenWizard.nonce
				},
				success: function(response) {
					if (response.success && response.data.redirect) {
						window.location.href = response.data.redirect;
					} else {
						$button.removeClass('loading').prop('disabled', false);
						alert('Failed to complete wizard');
					}
				},
				error: function(xhr) {
					$button.removeClass('loading').prop('disabled', false);
					alert('An error occurred. Please try again.');
				}
			});
		},
		
		showValidationMessage: function($container, type, message) {
			var $msg = $container.find('.seogen-wizard-validation-message');
			$msg.removeClass('success error info')
			    .addClass(type)
			    .text(message)
			    .show();
		},
		
		hideValidationMessage: function($container) {
			$container.find('.seogen-wizard-validation-message').hide();
		}
	};
	
	// Initialize on document ready
	$(document).ready(function() {
		if ($('.seogen-wizard-wrap').length) {
			SeogenWizard.init();
		}
	});
	
})(jQuery);
