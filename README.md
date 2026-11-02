# Botzio AI - Smart Chatbot & Website Assistant

Botzio AI helps WordPress site owners add a website-aware AI chatbot that answers visitor questions using saved business knowledge, selected pages/posts, and clear answer rules.

Unlike a generic chatbot, Botzio is designed to stay focused on the website it is installed on. Site owners can add business details, services, policies, support information, FAQs, starter questions, and limited page/post knowledge so the chatbot can give more relevant answers.

## What Botzio AI Does

- Connects WordPress to OpenAI, Gemini, or OpenRouter and loads compatible models directly from the provider.
- Lets admins build focused Business Knowledge for the chatbot.
- Syncs up to 3 selected WordPress pages or posts in the free version.
- Uses Smart Context Selection to send the most relevant saved knowledge to AI.
- Provides editable Answer Rules for safer, more consistent replies.
- Adds starter questions visitors can tap when chat opens.
- Remembers the latest four exchanges so visitors can ask natural follow-up questions.
- Collects helpful or not-helpful ratings and sends weak answers to Insights for review.
- Includes chatbot appearance controls and admin preview.
- Shows recent visitor questions, common topics, and AI connection status.
- Suggests Knowledge Gaps from recent visitor questions and setup state.
- Supports daily visitor message limits and adjustable answer length.
- Offers optional, consent-based installation analytics and deactivation feedback through Freemius; skipping tracking does not disable Botzio.

## Free vs Pro

Botzio AI Free provides the chatbot foundation:

- AI provider connection
- Business Knowledge builder
- Limited page/post sync
- Smart Context Selection
- Chatbot styling
- Starter questions
- Short-term conversation memory
- Visitor answer feedback
- Admin controls for memory length, feedback visibility, and stored-answer review data
- Usage limits
- Visitor question insights
- Knowledge Gap Suggestions

Botzio AI Pro is planned for advanced sales and support workflows:

- WooCommerce product answers
- Product recommendations
- Customer order lookup
- Lead capture
- Human handoff
- Conversation cart workflows
- Expanded knowledge automation

## Installation

1. Upload the `botzio-ai` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress Plugins screen.
3. Open **Botzio AI** from the WordPress admin menu.
4. Connect your preferred AI provider.
5. Add Business Knowledge and optionally sync selected pages/posts.
6. Customize and preview the chatbot before making it live.

## Supported AI Providers

- OpenAI
- Gemini
- OpenRouter

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher

## Optional Usage Analytics

Botzio AI can connect to Freemius after explicit administrator consent to report installation lifecycle, compatibility, and deactivation information. The consent screen can be skipped, and tracking can be disabled later from the WordPress Plugins screen. Botzio does not send AI provider keys, chatbot messages, AI answers, Business Knowledge, or synced page content to Freemius.

See the detailed external-service disclosure in [`readme.txt`](readme.txt).

## WordPress.org Readme

The official WordPress.org plugin metadata, changelog, FAQs, and marketplace description are maintained in [`readme.txt`](readme.txt).

## License

GPLv2 or later.
