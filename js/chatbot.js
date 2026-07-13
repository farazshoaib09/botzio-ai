jQuery(document).ready(function ($) {
  function escapeHtml(text) {
    return jQuery('<div>').text(text).html();
  }

  // Variable to ensure the welcome message is shown only once
  let welcomeShown = false;
  let isSending = false;

  function saveConversation() {
    localStorage.setItem(
      'botzio_ai_conversation',
      jQuery('#chatbot-window').html()
    );
  }

  function loadSavedConversation() {
    const saved = localStorage.getItem('botzio_ai_conversation');

    if (saved) {
      jQuery('#chatbot-window').html(saved);
      welcomeShown = true;

      setTimeout(function () {
        scrollChatToBottom();
      }, 100);

      return true;
    }

    return false;
  }

  function clearSavedConversation() {
    localStorage.removeItem('botzio_ai_conversation');
  }
  function formatAiText(text) {
    let safe = escapeHtml(text);

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

    isSending = true;

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
              : (res.data.message || 'Our agent is currently unavailable.');

          typeEffect(targetSpan, aiText, 15, function () {
            targetSpan.html(formatAiText(aiText));
            saveConversation();
          });
        } else {
          const errorMessage =
            res.data && res.data.message
              ? res.data.message
              : 'Something went wrong. Please try again later.';

          targetSpan.html(formatAiText(errorMessage));
          saveConversation();
        }
      },
      error: function () {
        typingElement
          .find('.chat-bubble')
          .html(formatAiText('Our agent is currently unavailable. Please try again later.'));

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
    localStorage.setItem('botzio_ai_is_open', isOpen ? 'yes' : 'no');
  }

  function isChatPreviouslyOpen() {
    return localStorage.getItem('botzio_ai_is_open') === 'yes';
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
