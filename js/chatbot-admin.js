jQuery(document).ready(function () {
  let botzioAiAdminModelsFetched = false;
  let botzioAiAdminCachedModels = botzioAiAdmin.cached_models || {};
  let botzioAiAdminConnectionVerified = !!botzioAiAdmin.connection_verified;
  let botzioAiAdminVerifiedProvider = botzioAiAdmin.verified_provider || '';
  let botzioAiAdminVerifiedModel = botzioAiAdmin.verified_model || '';
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
      ? provider.charAt(0).toUpperCase() + provider.slice(1) + ' connection verified' + (model ? ' - ' + model : '')
      : provider.charAt(0).toUpperCase() + provider.slice(1) + ' connection not verified';

    jQuery('.botzio-ai-status-dot')
      .toggleClass('connected', isVerified)
      .toggleClass('disconnected', !isVerified);

    jQuery('.botzio-ai-status-row strong').text(statusText);

    jQuery('.botzio-ai-pill')
      .toggleClass('is-success', isVerified)
      .toggleClass('is-warning', !isVerified)
      .text(isVerified ? 'Connection verified' : 'Connection pending');
  }

  function updateSaveButton() {
    const saveButton = jQuery('.botzio-ai-settings-actions input[type="submit"]');
    const needsTest = connectionNeedsTestBeforeSave();

    saveButton.prop('disabled', needsTest);
    saveButton.attr(
      'title',
      needsTest ? 'Please test the selected provider, API key, and model before saving.' : ''
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

  function updateConnectionControls() {
    updateConnectionStatusUi();
    updateModelFetchButton();
    updateChangeModelButton();
    updateTestConnectionButton();
    updateSaveButton();
  }

  function resetModelSelectionState() {
    botzioAiAdminModelsFetched = false;
    jQuery('#botzio-ai-model-dropdown')
      .hide()
      .html('<option value="">Select a model</option>');
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

  jQuery(document).on('click', '#botzio-ai-fetch-models', function () {
    const button = jQuery(this);
    const result = jQuery('#botzio-ai-model-result');
    const dropdown = jQuery('#botzio-ai-model-dropdown');
    const apiKey = getApiKey();
    const provider = getProvider();

    if (!apiKey) {
      result.css('color', 'red').text('Enter an API key before fetching models.');
      dropdown.hide().html('<option value="">Select a model</option>');
      updateConnectionControls();
      return;
    }

    button.prop('disabled', true).text('Fetching...');
    result.css('color', '').text('');
    dropdown.hide().html('<option value="">Select a model</option>');

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
          result.css('color', 'green').text('Models fetched successfully. Select a model, then test the connection.');
        } else {
          result.css('color', 'red').text('No models found.');
        }
      },
      error: function () {
        result.css('color', 'red').text('Unable to fetch models.');
      },
      complete: function () {
        button.text('Fetch Models');
        updateConnectionControls();
      }
    });
  });

  jQuery(document).on('click', '#botzio-ai-change-model', function () {
    const provider = getProvider();
    const result = jQuery('#botzio-ai-model-result');

    if (!hasCachedModels(provider)) {
      result.css('color', 'red').text('No cached models found. Fetch models again.');
      updateConnectionControls();
      return;
    }

    botzioAiAdminModelsFetched = true;
    populateModelDropdown(provider);
    jQuery('#botzio-ai-model-dropdown').show();
    result.css('color', '#5f6f82').text('Select a model, then test the connection before saving.');
    updateConnectionControls();
  });

  jQuery(document).on('click', '#botzio-ai-toggle-api-key', function () {
    const input = jQuery('#botzio_ai_api_key');
    const isPassword = input.attr('type') === 'password';

    input.attr('type', isPassword ? 'text' : 'password');
    jQuery(this).text(isPassword ? 'Hide' : 'Show');
  });

  jQuery(document).on('click', '#botzio-ai-reset-settings', function () {
    if (!confirm('Are you sure you want to reset all chatbot settings?')) {
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
          result.css('color', 'red').text('Unable to reset settings.');
        }
      },
      error: function () {
        result.css('color', 'red').text('Unable to reset settings.');
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
      jQuery('#botzio-ai-test-result').css('color', '#5f6f82').text('Please test this model before saving.');
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

          jQuery('#botzio-ai-model-dropdown').hide();
          result.css('color', 'green').text(res.data.message + ' You can now save settings.');
        } else {
          botzioAiAdminConnectionVerified = false;
          result.css('color', 'red').text(
            res.data && res.data.message ? res.data.message : 'Connection failed.'
          );
        }
      },
      error: function () {
        botzioAiAdminConnectionVerified = false;
        result.css('color', 'red').text('Connection failed.');
      },
      complete: function () {
        button.text('Test Connection');
        updateConnectionControls();
      }
    });
  });

  jQuery(document).on('click', '#botzio-ai-disconnect-api', function () {
    if (!confirm('Are you sure you want to disconnect the API?')) {
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
            res.data && res.data.message ? res.data.message : 'Disconnect failed.'
          );
        }
      },
      error: function () {
        result.css('color', 'red').text('Disconnect failed.');
      },
      complete: function () {
        button.prop('disabled', false).text('Disconnect API');
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
      .text('Please test the selected provider, API key, and model before saving.');
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

  const savedAdminTab = localStorage.getItem('botzio_ai_active_admin_tab');

  if (savedAdminTab && jQuery(savedAdminTab).length) {
    activateAdminTab(savedAdminTab);
  }

  function updateProviderHelpText() {
    const provider = getProvider();

    const data = {
      openrouter:
        'Need an API key? Create one from <a href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">OpenRouter API Keys</a>. Recommended model: <code>openrouter/free</code>.',
      openai:
        'Need an API key? Create one from <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">OpenAI API Keys</a>. Recommended model: <code>gpt-4o-mini</code>.',
      gemini:
        'Need an API key? Create one from <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>. Recommended model: <code>gemini-2.5-flash</code>.'
    };

    jQuery('#botzio-ai-provider-help-text').html(data[provider] || data.openrouter);
  }

  jQuery(document).on('change', '[name="botzio_ai_provider"]', function () {
    updateProviderHelpText();
  });

  updateProviderHelpText();
  initializeCachedModels();
});
