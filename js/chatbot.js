jQuery(document).ready(function ($) {
  function escapeHtml(text) {
    return jQuery('<div>').text(text).html();
  }

  // Variable to ensure the welcome message is shown only once
  let welcomeShown = false;
  let isSending = false;
  const conversationKey = 'botzio_ai_conversation';
  const historyKey = 'botzio_ai_conversation_messages';
  const sessionKey = 'botzio_ai_session_id';
  const memoryEnabled = Boolean(botzioAiFrontend.memory_enabled);
  const memoryMessageLimit = Math.max(0, parseInt(botzioAiFrontend.memory_messages, 10) || 0);
  const feedbackEnabled = Boolean(botzioAiFrontend.feedback_enabled);

  function readStorage(key) {
    try {
      return localStorage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function writeStorage(key, value) {
    try {
      localStorage.setItem(key, value);
    } catch (error) {
      // The chatbot still works when browser storage is blocked.
    }
  }

  function removeStorage(key) {
    try {
      localStorage.removeItem(key);
    } catch (error) {
      // Nothing else is required when browser storage is blocked.
    }
  }

  function loadConversationMessages() {
    if (!memoryEnabled || !memoryMessageLimit) {
      removeStorage(historyKey);
      return [];
    }

    const stored = readStorage(historyKey);

    if (!stored) return [];

    try {
      const messages = JSON.parse(stored);

      if (!Array.isArray(messages)) return [];

      return messages
        .filter(function (item) {
          return item && ['user', 'assistant'].includes(item.role) && typeof item.content === 'string';
        })
        .slice(-memoryMessageLimit);
    } catch (error) {
      return [];
    }
  }

  let conversationMessages = loadConversationMessages();

  function createSessionId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    return 'botzio-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
  }

  function getSessionId() {
    let sessionId = readStorage(sessionKey);

    if (!sessionId) {
      sessionId = createSessionId();
      writeStorage(sessionKey, sessionId);
    }

    return sessionId;
  }

  function rememberMessage(role, content) {
    if (!memoryEnabled || !memoryMessageLimit) return;

    const cleanContent = String(content || '').trim().slice(0, 800);

    if (!cleanContent || !['user', 'assistant'].includes(role)) return;

    conversationMessages.push({ role: role, content: cleanContent });
    conversationMessages = conversationMessages.slice(-memoryMessageLimit);
    writeStorage(historyKey, JSON.stringify(conversationMessages));
  }

  function saveConversation() {
    writeStorage(conversationKey, jQuery('#chatbot-window').html());
  }

  function loadSavedConversation() {
    const saved = readStorage(conversationKey);

    if (saved) {
      jQuery('#chatbot-window').html(saved);

      if (!feedbackEnabled) {
        jQuery('#chatbot-window .botzio-ai-answer-feedback').remove();
      }

      welcomeShown = true;

      setTimeout(function () {
        scrollChatToBottom();
      }, 100);

      return true;
    }

    return false;
  }

  function clearSavedConversation() {
    removeStorage(conversationKey);
    removeStorage(historyKey);
    removeStorage(sessionKey);
    conversationMessages = [];
  }

  function feedbackMarkup(responseId) {
    if (!feedbackEnabled || !responseId) return '';

    return (
      '<div class="botzio-ai-answer-feedback" data-response-id="' + escapeHtml(responseId) + '">' +
        '<span>' + escapeHtml(botzioAiFrontend.feedback_prompt) + '</span>' +
        '<div class="botzio-ai-feedback-actions">' +
          '<button type="button" data-rating="helpful">' + escapeHtml(botzioAiFrontend.feedback_yes) + '</button>' +
          '<button type="button" data-rating="not_helpful">' + escapeHtml(botzioAiFrontend.feedback_no) + '</button>' +
        '</div>' +
        '<small class="botzio-ai-feedback-result" aria-live="polite"></small>' +
      '</div>'
    );
  }
  function formatAiText(text) {
    let safe = escapeHtml(text);

    // Convert plain URLs into clickable links after escaping the AI text.
    safe = safe.replace(/https?:\/\/[^\s<]+/g, function (url) {
      const cleanUrl = url.replace(/[.,!?)]*$/, '');
      const trailing = url.slice(cleanUrl.length);
      const label = cleanUrl.indexOf('/product/') !== -1 ? 'Click here' : cleanUrl;

      return '<a href="' + cleanUrl + '" target="_blank" rel="noopener noreferrer">' + label + '</a>' + trailing;
    });

    // Bold markdown
    safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

    // Convert bullet lines at start OR after newline
    safe = safe.replace(/(^|\n)-\s+/g, '$1&bull; ');

    // Paragraph breaks
    safe = safe.replace(/\n\n/g, '</p><p>');

    // Single line breaks
    safe = safe.replace(/\n/g, '<br>');

    return '<p>' + safe + '</p>';
  }

  // Typing effect function
  function typeEffect(element, text, speed = 15, callback = null) {
    let i = 0;
    element.html('');

    function typing() {
      if (i < text.length) {
        element.append(escapeHtml(text.charAt(i)));
        i++;

        jQuery('#chatbot-window').scrollTop(jQuery('#chatbot-window')[0].scrollHeight);
        setTimeout(typing, speed);
      } else {
        if (typeof callback === 'function') {
          callback();
        }
      }
    }

    typing();
  }
  function showStarterSuggestions() {
    if (jQuery('#chatbot-window .chat-row').length > 1) {
      return;
    }

    const suggestions = Array.isArray(botzioAiFrontend.starter_suggestions)
    ? botzioAiFrontend.starter_suggestions
    : [];

  if (!suggestions.length) {
    return;
  }

    const suggestionWrap = jQuery('<div class="botzio-ai-suggestions"></div>');

    suggestions.forEach(function (text) {
      suggestionWrap.append(
        '<button type="button" class="botzio-ai-suggestion">' + escapeHtml(text) + '</button>'
      );
    });

    jQuery('#chatbot-window').append(suggestionWrap);
  }
  // Function to send user message and get AI response
  function sendMessage() {
    if (isSending) return;

    const text = jQuery('#chatbot-input').val().trim();
    if (!text) return;
    jQuery('.botzio-ai-suggestions').remove();

    const historyForRequest = memoryEnabled
      ? conversationMessages.slice(-memoryMessageLimit)
      : [];

    isSending = true;
    rememberMessage('user', text);

    jQuery('#chatbot-input').prop('disabled', true);
    jQuery('#chatbot-send').prop('disabled', true);

    // Append user message to the chat window
    jQuery('#chatbot-window').append(
    '<div class="chat-row user-row"><div class="chat-bubble user-bubble"><span class="botzio-ai-message-label">USER:</span> ' + escapeHtml(text) + '</div></div>'
  );

    jQuery('#chatbot-input').val('');

    // Add typing dots animation while waiting for the AI response
    const typingElement = jQuery(
      '<div class="chat-row ai-row"><div class="chat-bubble ai-bubble"><span class="typing-dots"><span></span><span></span><span></span></span></div></div>'
    );
    jQuery('#chatbot-window').append(typingElement);

    jQuery('#chatbot-window').scrollTop(jQuery('#chatbot-window')[0].scrollHeight);

    // Send the message via AJAX
    $.ajax({
      url: botzioAiFrontend.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
      action: 'botzio_ai_process',
      user_input: text,
      history: JSON.stringify(historyForRequest),
      session_id: getSessionId(),
      page_url: window.location.href,
      nonce: botzioAiFrontend.nonce

    },
      success: function (res) {
        const targetBubble = typingElement.find('.chat-bubble');
        targetBubble.html('<span class="botzio-ai-message-label">AI BOT:</span> <span class="typing"></span>');

        const targetSpan = targetBubble.find('.typing');

        if (res.success) {
          const aiText =
            typeof res.data === 'string'
              ? res.data
              : (res.data.message || 'I do not have enough information to answer that yet.');
          const responseId = typeof res.data === 'object' && res.data.response_id
            ? res.data.response_id
            : '';

          targetSpan.html(formatAiText(aiText) + feedbackMarkup(responseId));
          rememberMessage('assistant', aiText);
          saveConversation();
        } else {
          const errorMessage =
            res.data && res.data.message
              ? res.data.message
              : 'I am having trouble answering right now. Please try again in a moment.';

          targetSpan.html(formatAiText(errorMessage));
          saveConversation();
        }
      },
      error: function () {
        typingElement
          .find('.chat-bubble')
          .html(formatAiText('I am having trouble connecting right now. Please try again in a moment.'));

        saveConversation();
      },
      complete: function () {
        isSending = false;

        jQuery('#chatbot-input').prop('disabled', false);
        jQuery('#chatbot-send').prop('disabled', false);
        jQuery('#chatbot-input').focus();
      }
    });
  }
  jQuery(document).on('click', '.botzio-ai-suggestion', function () {
    const text = jQuery(this).text();

    jQuery('.botzio-ai-suggestions').remove();
    jQuery('#chatbot-input').val(text);

    sendMessage();
  });

  jQuery(document).on('click', '.botzio-ai-answer-feedback button', function () {
    const button = jQuery(this);
    const feedback = button.closest('.botzio-ai-answer-feedback');
    const buttons = feedback.find('button');
    const result = feedback.find('.botzio-ai-feedback-result');
    const rating = button.data('rating');

    if (feedback.hasClass('is-saved')) return;

    buttons.prop('disabled', true);
    result.text(botzioAiFrontend.feedback_saving);

    jQuery.ajax({
      url: botzioAiFrontend.ajax_url,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'botzio_ai_save_answer_feedback',
        response_id: feedback.data('response-id'),
        rating: rating,
        nonce: botzioAiFrontend.nonce
      },
      success: function (res) {
        if (!res.success) {
          buttons.prop('disabled', false);
          result.text(res.data && res.data.message ? res.data.message : botzioAiFrontend.feedback_error);
          return;
        }

        feedback.addClass('is-saved');
        button.addClass('is-selected');
        result.text(res.data.message || botzioAiFrontend.feedback_saved);
        saveConversation();
      },
      error: function () {
        buttons.prop('disabled', false);
        result.text(botzioAiFrontend.feedback_error);
      }
    });
  });
  // Function to show the welcome message once
  function showWelcomeMessage() {
    if (welcomeShown) return;

    jQuery('#chatbot-window').append(
      '<div class="chat-row ai-row"><div class="chat-bubble ai-bubble"><span class="botzio-ai-message-label">AI BOT:</span> <span class="welcome-msg">' + escapeHtml(botzioAiFrontend.welcome_message) + '</span></div></div>'
    );
    saveConversation();

    welcomeShown = true;
    jQuery('#chatbot-window').scrollTop(jQuery('#chatbot-window')[0].scrollHeight);
    showStarterSuggestions();
  }

  // Show chat when bubble is clicked
  jQuery('#botzio-ai-bubble').on('click', function () {
    const $bubble = jQuery(this);
    const $chat = jQuery('#chatbot-container');

    $bubble.css({
      opacity: '0',
      transform: 'scale(0.8)'
    });

    setTimeout(function () {
      $bubble.hide();

      $chat.removeClass('chatbot-hidden').addClass('chatbot-visible');
      saveChatOpenState(true);
      showWelcomeMessage();
      setTimeout(function () {
        scrollChatToBottom();
      }, 100);
    }, 200);
  });

  // Hide chat when close button is clicked
  jQuery('#chatbot-close').on('click', function () {
    const $bubble = jQuery('#botzio-ai-bubble');
    const $chat = jQuery('#chatbot-container');

    $chat.removeClass('chatbot-visible').addClass('chatbot-hidden');
    saveChatOpenState(false);

    setTimeout(function () {
      $bubble.show().css({
        opacity: '1',
        transform: 'scale(1)'
      });
    }, 300);
  });
  function scrollChatToBottom() {
    const chatWindow = jQuery('#chatbot-window');

    if (chatWindow.length) {
      chatWindow.scrollTop(chatWindow[0].scrollHeight);
    }
  }

  function saveChatOpenState(isOpen) {
    writeStorage('botzio_ai_is_open', isOpen ? 'yes' : 'no');
  }

  function isChatPreviouslyOpen() {
    return readStorage('botzio_ai_is_open') === 'yes';
  }  

  


  // Send message when the send button is clicked
  jQuery('#chatbot-send').on('click', function () {
    sendMessage();
  });

  // Send message when the enter key is pressed
  jQuery('#chatbot-input').on('keypress', function (e) {
    if (e.which === 13) {
      e.preventDefault();
      sendMessage();
    }
  });
  // jQuery('#chatbot-clear').on('click', function () {
  //   if (isSending) return;

  //   jQuery.ajax({
  //     url: botzioAiFrontend.ajax_url,
  //     method: 'POST',
  //     dataType: 'json',
  //     data: {
  //       action: 'botzio_ai_clear_conversation',
  //       nonce: botzioAiFrontend.nonce
  //     },
  //     success: function (res) {
  //       if (res.success) {
  //         welcomeShown = false;
  //         clearSavedConversation();
  //         jQuery('#chatbot-window').empty();
  //         showWelcomeMessage();
  //         saveConversation();
  //       }
  //     },
  //     error: function () {
  //       alert('Unable to clear conversation. Please try again.');
  //     }
  //   });
  // });
  jQuery('#chatbot-clear').on('click', function () {
    if (isSending) return;

    welcomeShown = false;
    clearSavedConversation();
    jQuery('#chatbot-window').empty();
    showWelcomeMessage();
    saveConversation();
  });

  

  // Show previous conversation and welcome message
  loadSavedConversation();

  if (isChatPreviouslyOpen()) {
    jQuery('#botzio-ai-bubble').hide();

    jQuery('#chatbot-container')
      .removeClass('chatbot-hidden')
      .addClass('chatbot-visible');

    setTimeout(function () {
      scrollChatToBottom();
    }, 150);
  }
  
});
