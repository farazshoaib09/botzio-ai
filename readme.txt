=== Botzio AI - Smart Chatbot & Website Assistant ===
Contributors: farazshoaib
Tags: ai chatbot, wordpress chatbot, customer support, ai assistant, knowledge base
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.14
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
- Admin-only chatbot preview mode
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

1. Frontend chatbot experience with a clean visitor-facing chat window.
2. Welcome setup guide that helps admins prepare knowledge, starter questions, design, and AI connection.
3. Main Botzio AI dashboard with connection, knowledge base, visitor chat, and launch progress status.
4. Business Knowledge tab for adding website details, offers, policies, support information, and FAQs.
5. Chat Experience settings with a live admin preview of the chatbot style.
6. Visitor Insights analytics showing total questions, common topics, review opportunities, and AI readiness.
7. AI Connection setup with provider, API key, model selection, and verified connection status.

== Changelog ==

= 1.0.14 =
* Simplified the top dashboard header actions to keep the setup area cleaner.
* Added a focused Pro comparison CTA that scrolls to and opens the Free vs Pro comparison section.

= 1.0.13 =
* Refined the configurator into focused in-popup steps for business info, starter questions, and chatbot preview.
* Added quick appearance controls inside the configurator so admins can preview title, color, welcome message, and bubble position before opening the full settings tab.
* Kept Connect AI as the final step that opens the full connection settings when the admin is ready to go live.
* Added clearer Free vs Pro messaging so admins can see what the free chatbot includes and which sales/support automations are available in Pro.
* Simplified the completed setup dashboard so settings tabs appear higher on the page and Pro details stay below the settings workflow.
* Polished admin headings, helper text, onboarding copy, and dashboard status cards with clearer business-focused wording.
* Added a launch progress checklist for incomplete setup and expanded Insights with total questions, AI readiness, topic trends, and questions that may need review.

= 1.0.12 =
* Changed the setup flow to start with lightweight business info, then starter questions, preview, and AI connection.
* Added a quick business info form in the configurator that saves directly into the knowledge base.
* Kept AI connection as the final go-live step while still allowing admins with API keys to connect anytime.

= 1.0.11 =
* Added an admin-only chatbot preview inside the Appearance tab so site owners can see the chatbot experience before connecting an AI provider.
* Added clickable preview questions that simulate how saved starter suggestions will appear in the chatbot.
* Kept the public frontend chatbot hidden until a verified AI connection or usable Pro module is available.

= 1.0.10 =
* Hid the frontend chatbot until a verified AI connection or usable Pro chatbot module is available.
* Added an Analytics tab with practical recent-question history.
* Improved the first-install welcome screen with choice-based setup paths.
* Added guided knowledge base starter blocks to help admins prepare useful chatbot context before connecting AI.

= 1.0.9 =
* Removed the experimental in-dashboard licensing integration from the free plugin build.
* Kept the free plugin focused on the WordPress.org distribution flow.
* The admin dashboard still detects Botzio AI Pro and hides the upgrade card when Pro is active.

= 1.0.8 =
* Restored the standard activation flow for the free plugin.

= 1.0.7 =
* Internal release testing update.

= 1.0.6 =
* Internal release testing update.

= 1.0.5 =
* Updated the free plugin dashboard to show Pro Active instead of upgrade messaging when Botzio AI Pro is already installed.

= 1.0.4 =
* Updated the Pro preview card with a View Pro Plans action.

= 1.0.3 =
* Added a first-install welcome guide that walks new admins through the recommended setup order.
* Added a persistent setup checklist for Configure AI, Knowledge Base, and Appearance setup.
* Refined the admin overview area with compact setup status, recent questions, and pro feature preview.
* Added cleanup for welcome guide options during uninstall.

= 1.0.2 =
* Redesigned the admin settings interface with a wider layout, hero summary, status cards, and icon tabs.
* Improved model selection workflow with cached provider models and connection retesting before saving model changes.
* Expanded large text fields and helper descriptions for better readability on full-width settings screens.
* Added cached model cleanup during uninstall/reset flows.

= 1.0.1 =
* Fixed uninstall cleanup logic.
* Fixed admin provider help script initialization.
* Fixed duplicated frontend chat bubble click handling.
* Fixed API connection verification status handling.
* Updated plugin headers and readme version consistency.
* Clarified external service data disclosure for the free version.
* Added custom chatbot launcher icon and subtle attention animation.
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
