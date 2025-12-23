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
			var serviceName = $input.val().trim();
			
			if (!serviceName) {
				alert('Please enter a service name');
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
					service_name: serviceName
				},
				success: function(response) {
					if (response.success) {
						$input.val('');
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
			
			$.ajax({
				url: seogenWizard.ajaxurl,
				method: 'POST',
				data: {
					action: 'seogen_wizard_start_generation',
					nonce: seogenWizard.nonce
				},
				success: function(response) {
					if (response.success) {
						// Show progress UI
						$('.seogen-wizard-generation-plan').hide();
						$('.seogen-wizard-generation-progress').show();
						
						// Start polling for progress
						self.pollGenerationProgress();
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
		
		onGenerationComplete: function() {
			$('.seogen-wizard-progress-details').html(
				'<p style="color: #46b450; font-weight: 600;">✓ All pages generated successfully!</p>' +
				'<p><a href="' + seogenWizard.adminUrl + 'edit.php?post_type=service_page" class="button button-primary">View Generated Pages</a></p>'
			);
			
			$('.seogen-wizard-start-generation').hide();
			$('.seogen-wizard-skip-generation').text('Complete Wizard').removeClass('button-secondary').addClass('button-primary').prop('disabled', false);
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
