=== Botzio AI - Smart Chatbot & Website Assistant ===
Contributors: farazshoaib
Tags: ai chatbot, wordpress chatbot, customer support, ai assistant, knowledge base
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered WordPress chatbot with OpenAI, Gemini, and OpenRouter support for automated customer support and website assistance.

== Description ==

**Botzio AI** helps WordPress website owners add an AI-powered chatbot to their website for customer support, FAQ automation, and visitor engagement.

The plugin supports multiple AI providers including OpenAI, Gemini, and OpenRouter, allowing you to configure your preferred AI model and train responses using your own business information and website knowledge base.

Botzio AI is designed for businesses, agencies, online stores, service providers, and website owners who want to automate common support questions and improve visitor interaction.

= Free Features =

- AI provider support:
  - OpenAI
  - Gemini
  - OpenRouter

- Custom knowledge base support
- Website-specific AI responses
- Chat bubble customization
- Custom welcome message
- Starter question suggestions
- Daily visitor message limits
- Adjustable AI temperature and response length
- Floating chatbot interface
- Multiple AI model support

== Installation ==

1. Upload the `botzio-ai` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Open the Botzio AI settings page from the WordPress admin menu.
4. Select your preferred AI provider.
5. Enter your API key and select a model.
6. Configure your chatbot appearance and knowledge base settings.

== Frequently Asked Questions ==

= Which AI providers are supported? =

The plugin currently supports:
- OpenAI
- Gemini
- OpenRouter

= Can I train the chatbot using my own business information? =

Yes. You can add custom business information, FAQs, policies, and support details through the built-in knowledge base settings.

= Can I customize the chatbot appearance? =

Yes. You can customize:
- Chat title
- Primary color
- Bubble position
- Welcome message

= Does the free version include message limits? =

Yes. The free version includes a daily message limit per visitor.

== External services ==

Botzio AI connects to third-party AI services selected by the website administrator to generate chatbot responses and retrieve available AI models.

= OpenRouter =

OpenRouter is used to generate AI responses and retrieve available AI models.

Data sent:
* Administrator API key.
* Visitor chat messages.
* Custom system prompt.
* Manual website knowledge base configured by the administrator.
* Selected AI model.

Data is sent:
* When a visitor sends a chatbot message.
* When the administrator tests the API connection.
* When the administrator refreshes the available model list.

Terms of Service:
https://openrouter.ai/terms

Privacy Policy:
https://openrouter.ai/privacy


= OpenAI =

OpenAI is used to generate AI responses and retrieve available AI models.

Data sent:
* Administrator API key.
* Visitor chat messages.
* Custom system prompt.
* Manual website knowledge base configured by the administrator.
* Selected AI model.

Data is sent:
* When a visitor sends a chatbot message.
* When the administrator tests the API connection.
* When the administrator refreshes the available model list.

Terms of Use:
https://openai.com/policies/terms-of-use

Privacy Policy:
https://openai.com/policies/privacy-policy


= Google Gemini =

Google Gemini (Generative Language API) is used to generate AI responses and retrieve available AI models.

Data sent:
* Administrator API key.
* Visitor chat messages.
* Custom system prompt.
* Manual website knowledge base configured by the administrator.
* Selected AI model.

Data is sent:
* When a visitor sends a chatbot message.
* When the administrator tests the API connection.
* When the administrator refreshes the available model list.

Gemini API Terms:
https://ai.google.dev/gemini-api/terms

Google Privacy Policy:
https://policies.google.com/privacy


== Screenshots ==

1. Frontend chatbot launcher.
2. Opened chatbot conversation window.
3. AI provider connection settings.
4. Verified API connection screen.
5. Knowledge Base / Content Settings / Starter Suggestions.
6. Chatbot appearance customization.
7. Usage controls: Daily Limit, Temperature, and Max Tokens.
8. Simple chatbot analytics overview.

== Changelog ==

= 1.0.1 =
* Fixed uninstall cleanup logic.
* Fixed admin provider help script initialization.
* Fixed duplicated frontend chat bubble click handling.
* Fixed API connection verification status handling.
* Updated plugin headers and readme version consistency.
* Clarified external service data disclosure for the free version.
* Added custom chatbot launcher icon and subtle attention animation.
* Improved model selection workflow so verified connections can reuse fetched models and require retesting before saving model changes.
* Removed unused internal code before deployment.

= 1.0.0 =
* Initial release.
* Added OpenAI, Gemini, and OpenRouter support.
* Added chatbot appearance customization.
* Added knowledge base functionality.
* Added daily message limits.
* Added starter suggestions support.

== License ==

This plugin is licensed under GPLv2 or later.
