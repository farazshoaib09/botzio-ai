jQuery(document).ready(function () {
  let botzioAiAdminModelsFetched = false;
  let botzioAiAdminCachedModels = botzioAiAdmin.cached_models || {};
  let botzioAiAdminConnectionVerified = !!botzioAiAdmin.connection_verified;
  let botzioAiAdminVerifiedProvider = botzioAiAdmin.verified_provider || '';
  let botzioAiAdminVerifiedModel = botzioAiAdmin.verified_model || '';
  let botzioAiAdminModelChangeMode = false;
  let botzioAiAdminVerifiedApiKey = botzioAiAdminConnectionVerified
    ? jQuery('[name="botzio_ai_api_key"]').val().trim()
    : '';

  function getProvider() {
    return jQuery('[name="botzio_ai_provider"]').val();
  }

  function getApiKey() {
    return jQuery('[name="botzio_ai_api_key"]').val().trim();
  }

  function getModel() {
    return jQuery('[name="botzio_ai_model"]').val().trim();
  }

  function getModelLabel(model, provider) {
    if (model.includes(':free') || model === 'openrouter/free') {
      return model + ' - Free';
    }

    if (provider === 'openai') {
      return model + ' - OpenAI';
    }

    if (provider === 'gemini') {
      return model + ' - Gemini';
    }

    if (provider === 'openrouter') {
      return model + ' - OpenRouter';
    }

    return model;
  }

  function getCachedModels(provider) {
    const models = botzioAiAdminCachedModels[provider];
    return Array.isArray(models) ? models : [];
  }

  function setCachedModels(provider, models) {
    botzioAiAdminCachedModels[provider] = Array.isArray(models) ? models : [];
  }

  function hasCachedModels(provider) {
    return getCachedModels(provider).length > 0;
  }

  function populateModelDropdown(provider) {
    const dropdown = jQuery('#botzio-ai-model-dropdown');
    const currentModel = getModel();
    const models = getCachedModels(provider);

    dropdown.html('<option value="">Select a model</option>');

    models.forEach(function (model) {
      dropdown.append(
        jQuery('<option>', {
          value: model,
          text: getModelLabel(model, provider)
        })
      );
    });

    if (currentModel && models.includes(currentModel)) {
      dropdown.val(currentModel);
    }
  }

  function currentConnectionMatchesVerified() {
    return (
      botzioAiAdminConnectionVerified &&
      getProvider() === botzioAiAdminVerifiedProvider &&
      getApiKey() === botzioAiAdminVerifiedApiKey &&
      getModel() === botzioAiAdminVerifiedModel
    );
  }

  function connectionNeedsTestBeforeSave() {
    return !!(getApiKey() && (!getModel() || !currentConnectionMatchesVerified()));
  }

  function updateConnectionStatusUi() {
    const provider = getProvider();
    const model = getModel();
    const isVerified = currentConnectionMatchesVerified();
    const statusText = isVerified
      ? provider.charAt(0).toUpperCase() + provider.slice(1) + ' is ready' + (model ? ' - ' + model : '')
      : provider.charAt(0).toUpperCase() + provider.slice(1) + ' needs testing';

    jQuery('.botzio-ai-status-dot')
      .toggleClass('connected', isVerified)
      .toggleClass('disconnected', !isVerified);

    jQuery('.botzio-ai-status-row strong').text(statusText);

    jQuery('.botzio-ai-connection-pill')
      .toggleClass('is-success', isVerified)
      .toggleClass('is-warning', !isVerified)
      .text(isVerified ? 'AI ready' : 'AI not ready');
  }

  function updateSaveButton() {
    const saveButton = jQuery('.botzio-ai-settings-actions input[type="submit"]');
    const needsTest = connectionNeedsTestBeforeSave();

    saveButton.prop('disabled', needsTest);
    saveButton.attr(
      'title',
      needsTest ? 'Test the selected provider, API key, and model before saving changes.' : ''
    );
  }

  function updateTestConnectionButton() {
    const canTest = !!(getProvider() && getApiKey() && getModel() && botzioAiAdminModelsFetched);

    jQuery('#botzio-ai-test-connection').prop('disabled', !canTest);
  }

  function updateModelFetchButton() {
    const canFetch = !!(
      getProvider() &&
      getApiKey() &&
      !botzioAiAdminModelsFetched &&
      (!currentConnectionMatchesVerified() || !hasCachedModels(getProvider()))
    );

    jQuery('#botzio-ai-fetch-models').prop('disabled', !canFetch);
  }

  function updateChangeModelButton() {
    const provider = getProvider();
    const shouldShow = currentConnectionMatchesVerified() && hasCachedModels(provider);

    jQuery('#botzio-ai-change-model').toggle(shouldShow);
  }

  function updateModelInputLock() {
    const shouldLock = currentConnectionMatchesVerified() && !botzioAiAdminModelChangeMode;

    jQuery('#botzio_ai_model')
      .prop('readonly', shouldLock)
      .toggleClass('botzio-ai-readonly-model', shouldLock);
  }

  function updateConnectionControls() {
    updateConnectionStatusUi();
    updateModelFetchButton();
    updateChangeModelButton();
    updateTestConnectionButton();
    updateModelInputLock();
    updateSaveButton();
  }

  function resetModelSelectionState() {
    botzioAiAdminModelsFetched = false;
    botzioAiAdminModelChangeMode = false;
    jQuery('#botzio-ai-model-dropdown')
      .hide()
      .html('<option value="">Select a model for Botzio</option>');
    jQuery('#botzio_ai_model').val('');
    jQuery('#botzio-ai-model-result').text('');
    jQuery('#botzio-ai-test-result').text('');
    updateConnectionControls();
  }

  function initializeCachedModels() {
    const provider = getProvider();

    if (hasCachedModels(provider)) {
      botzioAiAdminModelsFetched = true;
      populateModelDropdown(provider);
    }

    updateConnectionControls();
  }

  function dismissWelcomeGuide() {
    const overlay = jQuery('.botzio-ai-welcome-overlay');

    if (!overlay.length) {
      return;
    }

    overlay.fadeOut(180, function () {
      jQuery(this).addClass('is-hidden').removeAttr('style');
    });

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_dismiss_welcome',
        nonce: botzioAiAdmin.nonce
      }
    });
  }

  jQuery(document).on('click', '.botzio-ai-welcome-close, .botzio-ai-welcome-skip', function () {
    dismissWelcomeGuide();
  });

  jQuery(document).on('click', '.botzio-ai-welcome-action', function () {
    dismissWelcomeGuide();
  });

  jQuery(document).on('click', '.botzio-ai-open-configurator', function () {
    const overlay = jQuery('.botzio-ai-welcome-overlay');

    if (!overlay.length) {
      return;
    }

    overlay
      .removeClass('is-hidden')
      .hide()
      .fadeIn(180);
  });

  function showWelcomePanel(panel) {
    const modal = jQuery('.botzio-ai-welcome-modal');

    jQuery('.botzio-ai-welcome-panel').removeClass('is-active');
    jQuery('.botzio-ai-welcome-panel[data-panel="' + panel + '"]').addClass('is-active');

    if (modal.length) {
      modal.scrollTop(0);
    }
  }

  function markWelcomeStepComplete(step) {
    const progressItems = jQuery('.botzio-ai-welcome-progress li');
    const progressItem = progressItems.filter('[data-welcome-step="' + step + '"]');
    const option = jQuery('.botzio-ai-welcome-option[data-welcome-step="' + step + '"]');
    const status = option.find('.botzio-ai-welcome-option-status');
    const total = progressItems.length || 4;

    progressItem
      .removeClass('is-pending')
      .addClass('is-complete')
      .find('.dashicons')
      .removeClass('dashicons-marker')
      .addClass('dashicons-yes-alt');

    status
      .removeClass('is-pending')
      .addClass('is-complete')
      .text('Completed');

    const completed = progressItems.filter('.is-complete').length;
    const percent = Math.min(100, Math.round((completed / total) * 100));

    jQuery('.botzio-ai-welcome-progress strong').text(completed + ' of ' + total + ' completed');
    jQuery('.botzio-ai-welcome-progress-bar span').css('width', percent + '%');
  }

  jQuery(document).on('click', '.botzio-ai-open-welcome-panel, .botzio-ai-welcome-back', function (event) {
    const panel = jQuery(this).data('panel');

    if (!panel) {
      return;
    }

    event.preventDefault();
    showWelcomePanel(panel);
  });

  jQuery(document).on('click', '#botzio-ai-save-welcome-suggestions', function () {
    const button = jQuery(this);
    const result = jQuery('#botzio-ai-welcome-suggestions-result');
    const suggestions = jQuery('#botzio-ai-welcome-starter-suggestions').val();

    button.prop('disabled', true).text('Saving...');
    result.css('color', '').text('');

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_save_welcome_suggestions',
        nonce: botzioAiAdmin.nonce,
        suggestions: suggestions
      },
      success: function (res) {
        if (res.success) {
          jQuery('[name="botzio_ai_starter_suggestions"]').val(suggestions);
          result.css('color', 'green').text(res.data.message || 'Starter questions saved.');
          markWelcomeStepComplete('suggestions');
          setTimeout(function () {
            showWelcomePanel('preview');
          }, 350);
        } else {
          result.css('color', 'red').text('Could not save starter questions. Please try again.');
        }
      },
      error: function () {
        result.css('color', 'red').text('Could not save starter questions. Please try again.');
      },
      complete: function () {
        button.prop('disabled', false).text('Save & Continue');
      }
    });
  });

  jQuery(document).on('click', '#botzio-ai-save-welcome-business-info', function () {
    const button = jQuery(this);
    const result = jQuery('#botzio-ai-welcome-business-result');

    button.prop('disabled', true).text('Saving...');
    result.css('color', '').text('');

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_save_welcome_business_info',
        nonce: botzioAiAdmin.nonce,
        business_name: jQuery('#botzio-ai-welcome-business-name').val(),
        business_offer: jQuery('#botzio-ai-welcome-business-offer').val(),
        audience: jQuery('#botzio-ai-welcome-business-audience').val(),
        support: jQuery('#botzio-ai-welcome-business-support').val()
      },
      success: function (res) {
        if (res.success) {
          if (res.data && typeof res.data.knowledge_base === 'string') {
            jQuery('#botzio_knowledge_base').val(res.data.knowledge_base);
          }

          result.css('color', 'green').text(res.data.message || 'Business basics saved.');
          markWelcomeStepComplete('business');
          setTimeout(function () {
            showWelcomePanel('suggestions');
          }, 350);
        } else {
          result
            .css('color', 'red')
            .text(res.data && res.data.message ? res.data.message : 'Could not save business basics. Please try again.');
        }
      },
      error: function () {
        result.css('color', 'red').text('Could not save business basics. Please try again.');
      },
      complete: function () {
        button.prop('disabled', false).text('Save & Continue');
      }
    });
  });

  jQuery(document).on('click', '#botzio-ai-save-welcome-appearance', function () {
    const button = jQuery(this);
    const result = jQuery('#botzio-ai-welcome-appearance-result');

    button.prop('disabled', true).text('Saving...');
    result.css('color', '').text('');

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_save_welcome_appearance',
        nonce: botzioAiAdmin.nonce,
        chat_title: jQuery('#botzio-ai-welcome-chat-title').val(),
        primary_color: jQuery('#botzio-ai-welcome-primary-color').val(),
        welcome_message: jQuery('#botzio-ai-welcome-message-preview').val(),
        bubble_position: jQuery('#botzio-ai-welcome-bubble-position').val()
      },
      success: function (res) {
        if (res.success) {
          result.css('color', 'green').text('Chat preview saved.');
          jQuery('.botzio-ai-preview-next').removeClass('is-hidden');
          markWelcomeStepComplete('preview');
        } else {
          result.css('color', 'red').text('Could not save the chat preview. Please try again.');
        }
      },
      error: function () {
        result.css('color', 'red').text('Could not save the chat preview. Please try again.');
      },
      complete: function () {
        button.prop('disabled', false).text('Save Preview');
      }
    });
  });

  function getSelectedSourceIds() {
    return jQuery('.botzio-ai-source-checkbox:checked')
      .map(function () {
        return jQuery(this).val();
      })
      .get();
  }

  function updateSourceSyncUi() {
    const selected = getSelectedSourceIds();
    const count = selected.length;
    const limitReached = count >= 3;

    jQuery('#botzio-ai-source-count').text(count);

    jQuery('.botzio-ai-source-checkbox').each(function () {
      const checkbox = jQuery(this);
      checkbox.prop('disabled', limitReached && !checkbox.prop('checked'));
    });

    jQuery('#botzio-ai-sync-sources').prop('disabled', count < 1);
  }

  jQuery(document).on('change', '.botzio-ai-source-checkbox', function () {
    const checkbox = jQuery(this);
    const result = jQuery('#botzio-ai-source-sync-result');

    if (getSelectedSourceIds().length > 3) {
      checkbox.prop('checked', false);
      result.css('color', 'red').text('Free sync is limited to 3 pages or posts.');
    } else {
      result.css('color', '').text('');
    }

    updateSourceSyncUi();
  });

  jQuery(document).on('click', '#botzio-ai-sync-sources', function () {
    const button = jQuery(this);
    const result = jQuery('#botzio-ai-source-sync-result');
    const sourceIds = getSelectedSourceIds();

    if (!sourceIds.length) {
      result.css('color', 'red').text('Select at least one page or post to sync.');
      return;
    }

    button.prop('disabled', true).text('Syncing...');
    result.css('color', '').text('');

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_sync_selected_sources',
        nonce: botzioAiAdmin.nonce,
        source_ids: sourceIds
      },
      success: function (res) {
        if (res.success) {
          if (res.data && typeof res.data.knowledge_base === 'string') {
            jQuery('#botzio_knowledge_base').val(res.data.knowledge_base);
          }

          result.css('color', 'green').text(res.data.message || 'Sources synced.');
        } else {
          result
            .css('color', 'red')
            .text(res.data && res.data.message ? res.data.message : 'Could not sync selected sources.');
        }
      },
      error: function () {
        result.css('color', 'red').text('Could not sync selected sources.');
      },
      complete: function () {
        button.text('Sync Selected Sources');
        updateSourceSyncUi();
      }
    });
  });

  function updateRecentQuestionLimit() {
    const limit = parseInt(jQuery('#botzio-ai-question-limit').val(), 10) || 10;
    const rows = jQuery('.botzio-ai-recent-question-list li');
    let visibleCount = 0;

    rows.each(function (index) {
      const row = jQuery(this);
      const questionIndex = parseInt(row.attr('data-question-index'), 10);
      const shouldShow = (Number.isNaN(questionIndex) ? index : questionIndex) < limit;

      row.toggleClass('is-hidden-by-filter', !shouldShow);

      if (shouldShow) {
        visibleCount += 1;
      }
    });

    jQuery('#botzio-ai-question-filter-count').text(
      rows.length ? 'Showing ' + visibleCount + ' of ' + rows.length + ' questions' : ''
    );
  }

  jQuery(document).on('change', '#botzio-ai-question-limit', updateRecentQuestionLimit);

  function renderAdminPreviewAnswer(question) {
    const answerWrap = jQuery('.botzio-ai-preview-answer');
    const answerText =
      'Sample preview: once AI is connected, Botzio can answer "' +
      question +
      '" using your saved business knowledge and site instructions.';

    if (!answerWrap.length || !question) {
      return;
    }

    answerWrap.empty();

    answerWrap
      .append(
        jQuery('<div>', { class: 'botzio-ai-preview-row is-user' }).append(
          jQuery('<div>', { class: 'botzio-ai-preview-bubble', text: question })
        )
      )
      .append(
        jQuery('<div>', { class: 'botzio-ai-preview-row is-bot' }).append(
          jQuery('<div>', { class: 'botzio-ai-preview-bubble' })
            .append(jQuery('<span>', { text: 'Botzio:' }))
            .append(document.createTextNode(answerText))
        )
      );
  }

  jQuery(document).on('click', '.botzio-ai-preview-question', function () {
    const button = jQuery(this);
    const question = button.data('question') || button.text().trim();

    jQuery('.botzio-ai-preview-question').removeClass('is-active');
    button.addClass('is-active');
    renderAdminPreviewAnswer(question);
  });

  function updateAppearancePreview() {
    const color = jQuery('#botzio-ai-welcome-primary-color').val() || jQuery('[name="botzio_ai_primary_color"]').val() || '#111111';
    const title =
      (jQuery('#botzio-ai-welcome-chat-title').val() || jQuery('[name="botzio_ai_chat_title"]').val() || '').trim() ||
      'Chat Support';
    const welcomeMessage =
      (jQuery('#botzio-ai-welcome-message-preview').val() || jQuery('[name="botzio_ai_welcome_message"]').val() || '').trim() ||
      'Hello! Welcome to our website. How can I help you today?';

    jQuery('.botzio-ai-preview-phone').css('--botzio-preview-primary', color);
    jQuery('.botzio-ai-preview-title').text(title);
    jQuery('.botzio-ai-preview-welcome-message').text(welcomeMessage);
  }

  jQuery(document).on(
    'input change keyup',
    '[name="botzio_ai_primary_color"], [name="botzio_ai_chat_title"], [name="botzio_ai_welcome_message"], #botzio-ai-welcome-primary-color, #botzio-ai-welcome-chat-title, #botzio-ai-welcome-message-preview',
    function () {
      const source = jQuery(this);

      if (source.attr('id') === 'botzio-ai-welcome-primary-color') {
        jQuery('[name="botzio_ai_primary_color"]').val(source.val());
      } else if (source.attr('id') === 'botzio-ai-welcome-chat-title') {
        jQuery('[name="botzio_ai_chat_title"]').val(source.val());
      } else if (source.attr('id') === 'botzio-ai-welcome-message-preview') {
        jQuery('[name="botzio_ai_welcome_message"]').val(source.val());
      } else if (source.attr('name') === 'botzio_ai_primary_color') {
        jQuery('#botzio-ai-welcome-primary-color').val(source.val());
      } else if (source.attr('name') === 'botzio_ai_chat_title') {
        jQuery('#botzio-ai-welcome-chat-title').val(source.val());
      } else if (source.attr('name') === 'botzio_ai_welcome_message') {
        jQuery('#botzio-ai-welcome-message-preview').val(source.val());
      }

      updateAppearancePreview();
    }
  );

  const knowledgeTemplates = {
    business:
      'Business Introduction:\n- Business name:\n- What we offer:\n- Who we help:\n- Main location or service area:\n- Important brand promise:\n',
    products:
      'Products / Services:\n- Main products or services:\n- Popular categories:\n- Price range or starting price:\n- What customers usually ask before buying:\n',
    policies:
      'Policies:\n- Shipping / delivery policy:\n- Return / exchange policy:\n- Payment methods:\n- Warranty or service policy:\n',
    support:
      'Support Information:\n- Support email:\n- Support phone / WhatsApp:\n- Business hours:\n- What visitors should do for urgent help:\n',
    faq:
      'FAQs:\nQ: \nA: \n\nQ: \nA: \n'
  };

  jQuery(document).on('click', '.botzio-ai-kb-template', function () {
    const templateKey = jQuery(this).data('template');
    const template = knowledgeTemplates[templateKey];
    const textarea = jQuery('#botzio_knowledge_base');

    if (!template || !textarea.length) {
      return;
    }

    const currentValue = textarea.val().trim();
    const nextValue = currentValue ? currentValue + '\n\n' + template : template;

    textarea.val(nextValue).trigger('focus');
  });

  jQuery(document).on('click', '#botzio-ai-fetch-models', function () {
    const button = jQuery(this);
    const result = jQuery('#botzio-ai-model-result');
    const dropdown = jQuery('#botzio-ai-model-dropdown');
    const apiKey = getApiKey();
    const provider = getProvider();

    if (!apiKey) {
      result.css('color', 'red').text('Paste your API key before finding available models.');
      dropdown.hide().html('<option value="">Select a model for Botzio</option>');
      updateConnectionControls();
      return;
    }

    button.prop('disabled', true).text('Finding...');
    result.css('color', '').text('');
    dropdown.hide().html('<option value="">Select a model for Botzio</option>');

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_fetch_models',
        nonce: botzioAiAdmin.nonce,
        provider: provider,
        api_key: jQuery('[name="botzio_ai_api_key"]').val()
      },
      success: function (res) {
        if (res.success && res.data.models && res.data.models.length > 0) {
          setCachedModels(provider, res.data.models);
          populateModelDropdown(provider);
          botzioAiAdminModelsFetched = true;

          dropdown.show();
          result.css('color', 'green').text('Models found. Choose one, then test the AI connection.');
        } else {
          result.css('color', 'red').text('No models were found for this provider.');
        }
      },
      error: function () {
        result.css('color', 'red').text('Could not load models. Please check the API key and try again.');
      },
      complete: function () {
        button.text('Find Models');
        updateConnectionControls();
      }
    });
  });

  jQuery(document).on('click', '#botzio-ai-change-model', function () {
    const provider = getProvider();
    const result = jQuery('#botzio-ai-model-result');

    if (!hasCachedModels(provider)) {
      result.css('color', 'red').text('No saved model list is available. Find models again.');
      updateConnectionControls();
      return;
    }

    botzioAiAdminModelChangeMode = true;
    botzioAiAdminModelsFetched = true;
    populateModelDropdown(provider);
    jQuery('#botzio_ai_model').prop('readonly', false).removeClass('botzio-ai-readonly-model').focus();
    jQuery('#botzio-ai-model-dropdown').show();
    result.css('color', '#5f6f82').text('Choose a model, then test the AI connection before saving.');
    updateConnectionControls();
  });

  jQuery(document).on('click', '#botzio-ai-toggle-api-key', function () {
    const input = jQuery('#botzio_ai_api_key');
    const isPassword = input.attr('type') === 'password';

    input.attr('type', isPassword ? 'text' : 'password');
    jQuery(this).text(isPassword ? 'Hide' : 'Show');
  });

  jQuery(document).on('click', '#botzio-ai-reset-settings', function () {
    if (!confirm('Restore Botzio to its default settings? This will remove your current chatbot configuration.')) {
      return;
    }

    const result = jQuery('#botzio-ai-reset-result');
    const button = jQuery(this);

    button.prop('disabled', true);

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_reset_settings',
        nonce: botzioAiAdmin.nonce
      },
      success: function (res) {
        if (res.success) {
          result.css('color', 'green').text(res.data.message);

          setTimeout(function () {
            location.reload();
          }, 800);
        } else {
          result.css('color', 'red').text('Could not restore defaults. Please try again.');
        }
      },
      error: function () {
        result.css('color', 'red').text('Could not restore defaults. Please try again.');
      },
      complete: function () {
        button.prop('disabled', false);
      }
    });
  });

  jQuery(document).on(
    'change keyup',
    '[name="botzio_ai_provider"], [name="botzio_ai_api_key"]',
    function () {
      resetModelSelectionState();
    }
  );

  jQuery(document).on('change keyup', '[name="botzio_ai_model"]', function () {
    updateConnectionControls();
  });

  jQuery(document).on('change', '#botzio-ai-model-dropdown', function () {
    const selectedModel = jQuery(this).val();

    if (selectedModel) {
      jQuery('#botzio_ai_model').val(selectedModel);
      jQuery('#botzio-ai-test-result').css('color', '#5f6f82').text('Test this model before saving your changes.');
      updateConnectionControls();
    }
  });

  jQuery(document).on('click', '#botzio-ai-test-connection', function () {
    const button = jQuery(this);
    const result = jQuery('#botzio-ai-test-result');

    button.prop('disabled', true).text('Testing...');
    result.css('color', '').text('');

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_test_connection',
        nonce: botzioAiAdmin.nonce,
        provider: getProvider(),
        api_key: jQuery('[name="botzio_ai_api_key"]').val(),
        model: getModel()
      },
      success: function (res) {
        if (res.success) {
          botzioAiAdminConnectionVerified = true;
          botzioAiAdminVerifiedProvider = getProvider();
          botzioAiAdminVerifiedApiKey = getApiKey();
          botzioAiAdminVerifiedModel = getModel();
          botzioAiAdminModelChangeMode = false;

          jQuery('#botzio-ai-model-dropdown').hide();
          result.css('color', 'green').text(res.data.message + ' You can now save your changes.');
        } else {
          botzioAiAdminConnectionVerified = false;
          result.css('color', 'red').text(
            res.data && res.data.message ? res.data.message : 'AI connection failed.'
          );
        }
      },
      error: function () {
        botzioAiAdminConnectionVerified = false;
        result.css('color', 'red').text('AI connection failed.');
      },
      complete: function () {
        button.text('Test AI Connection');
        updateConnectionControls();
      }
    });
  });

  jQuery(document).on('click', '#botzio-ai-disconnect-api', function () {
    if (!confirm('Disconnect this AI provider from Botzio?')) {
      return;
    }

    const button = jQuery(this);
    const result = jQuery('#botzio-ai-test-result');

    button.prop('disabled', true).text('Disconnecting...');
    result.css('color', '').text('');

    jQuery.ajax({
      url: botzioAiAdmin.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_disconnect_api',
        nonce: botzioAiAdmin.nonce
      },
      success: function (res) {
        if (res.success) {
          result.css('color', 'green').text(res.data.message);

          setTimeout(function () {
            window.location.reload();
          }, 800);
        } else {
          result.css('color', 'red').text(
            res.data && res.data.message ? res.data.message : 'Could not disconnect AI.'
          );
        }
      },
      error: function () {
        result.css('color', 'red').text('Could not disconnect AI.');
      },
      complete: function () {
        button.prop('disabled', false).text('Disconnect AI');
      }
    });
  });

  jQuery(document).on('submit', '.botzio-ai-settings-form', function (event) {
    if (!connectionNeedsTestBeforeSave()) {
      return;
    }

    event.preventDefault();
    activateAdminTab('#botzio-ai-tab-connection');
    jQuery('#botzio-ai-test-result')
      .css('color', 'red')
      .text('Test the selected provider, API key, and model before saving changes.');
  });

  function activateAdminTab(target) {
    jQuery('.botzio-ai-tabs .nav-tab').removeClass('nav-tab-active');
    jQuery('.botzio-ai-tabs .nav-tab[href="' + target + '"]').addClass('nav-tab-active');

    jQuery('.botzio-ai-tab-content').removeClass('active');
    jQuery(target).addClass('active');

    localStorage.setItem('botzio_ai_active_admin_tab', target);
  }

  jQuery(document).on('click', '.botzio-ai-tabs .nav-tab', function (e) {
    e.preventDefault();

    activateAdminTab(jQuery(this).attr('href'));
  });

  jQuery(document).on('click', '.botzio-ai-jump-tab', function (e) {
    e.preventDefault();

    const target = jQuery(this).attr('href');
    activateAdminTab(target);

    if (jQuery(target).length) {
      jQuery('html, body').animate({
        scrollTop: jQuery('.botzio-ai-tabs').offset().top - 32
      }, 250);
    }
  });

  jQuery(document).on('click', '.botzio-ai-welcome-sync-jump', function (e) {
    e.preventDefault();

    dismissWelcomeGuide();
    activateAdminTab('#botzio-ai-tab-content');

    const syncCard = jQuery('.botzio-ai-source-sync');
    const targetOffset = syncCard.length ? syncCard.offset().top : jQuery('.botzio-ai-tabs').offset().top;

    jQuery('html, body').animate({
      scrollTop: targetOffset - 32
    }, 300);
  });

  jQuery(document).on('click', '.botzio-ai-scroll-pro-comparison', function (e) {
    e.preventDefault();

    const target = jQuery(this).attr('href');
    const comparison = jQuery(target);

    if (!comparison.length) {
      return;
    }

    comparison.prop('open', true);

    jQuery('html, body').animate({
      scrollTop: comparison.offset().top - 32
    }, 300);
  });

  const savedAdminTab = localStorage.getItem('botzio_ai_active_admin_tab');

  if (savedAdminTab && jQuery(savedAdminTab).length) {
    activateAdminTab(savedAdminTab);
  }

  function updateProviderHelpText() {
    const provider = getProvider();

    const data = {
      openrouter:
        'Need an API key? Create one from <a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">OpenRouter API Keys</a>. Recommended starter model: <code>openrouter/free</code>.',
      openai:
        'Need an API key? Create one from <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">OpenAI API Keys</a>. Recommended starter model: <code>gpt-4o-mini</code>.',
      gemini:
        'Need an API key? Create one from <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>. Recommended starter model: <code>gemini-2.5-flash</code>.'
    };

    jQuery('#botzio-ai-provider-help-text').html(data[provider] || data.openrouter);
  }

  jQuery(document).on('change', '[name="botzio_ai_provider"]', function () {
    updateProviderHelpText();
  });

  updateProviderHelpText();
  initializeCachedModels();
  updateRecentQuestionLimit();
  updateSourceSyncUi();
  updateAppearancePreview();
});
