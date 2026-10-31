# 🧠 BrainLoop Roadmap

This roadmap outlines the planned development of **BrainLoop — Learn it. Keep it.** It highlights completed functionality, upcoming improvements, and potential long-term features.

The roadmap is intended to evolve as the project grows. Priorities and implementation details may change based on user feedback, technical requirements, and development progress.

**Note:** Only features explicitly marked as completed are currently implemented. All unchecked items represent proposed future work.

---

## Phase 1 — Core Browser MVP <sup>✅ Completed</sup>

Establish the foundation of BrainLoop with essential flashcard creation, study, and progress-tracking functionality.

- [x] Create, edit, and delete flashcard decks
- [x] Create, edit, and delete individual flashcards
- [x] Study flashcards using interactive card flipping
- [x] Rate answers based on personal recall and understanding
- [x] Schedule reviews using a basic interval-based system
- [x] Track learning progress locally in the browser
- [x] Export and import decks using JSON backups
- [x] Provide a responsive interface for desktop and mobile devices

## Phase 2 — Enhanced Learning Experience <sup>📋 Planned</sup>

Make everyday studying more effective, flexible, and accessible.

- [ ] Support CSV import for faster flashcard creation
- [ ] Add rich-text formatting, images, and code blocks to flashcards
- [ ] Introduce review history, progress charts, and study statistics
- [ ] Add optional study reminders and configurable daily goals
- [ ] Improve the review scheduler, with potential support for FSRS
- [ ] Enhance keyboard navigation, dialog focus management, and accessibility
- [ ] Expand automated accessibility testing
- [ ] Introduce automated unit tests and end-to-end UI tests

## Phase 3 — Accounts and Cloud Synchronization <sup>🔮 Future</sup>

Move beyond browser-only storage and make learning progress accessible across devices.

- [ ] Implement secure user registration and authentication
- [ ] Develop a backend API and database
- [ ] Store decks, flashcards, and review history in the cloud
- [ ] Synchronize learning data across browsers and devices
- [ ] Implement reliable backup, recovery, and conflict-resolution mechanisms
- [ ] Provide secure account deletion and data export options

## Phase 4 — AI-Assisted Learning <sup>💡 Future</sup>

Explore AI-powered features that help users create better flashcards and study more efficiently.

- [ ] Generate draft flashcards from pasted notes or study materials
- [ ] Identify ambiguous questions and suggest clearer wording
- [ ] Suggest answers, explanations, and relevant examples
- [ ] Allow users to review and edit AI-generated content before saving
- [ ] Explore automatic organization of generated cards into relevant decks
- [ ] Implement secure server-side API key management
- [ ] Add usage limits, error handling, and privacy safeguards

---

## Development Principles

As BrainLoop evolves, development should remain focused on the following principles:

- **Learning first:** Prioritize features that improve active recall, knowledge retention, and consistent study habits.
- **Simplicity:** Keep the interface intuitive and avoid unnecessary complexity.
- **Privacy:** Give users control over their learning data and how it is stored.
- **Accessibility:** Make the application usable across different devices and abilities.
- **Reliability:** Prioritize data integrity, stable functionality, and thorough testing.
- **Progressive development:** Build and validate core functionality before introducing complex features.

## Security Considerations

> **Never expose private API keys, database credentials, or other secrets in browser-side JavaScript or public GitHub repositories.**

BrainLoop's current GitHub Pages deployment uses static hosting. Features requiring protected API credentials, authentication, or cloud storage will need a secure backend rather than exposing sensitive information in the frontend.

---

*BrainLoop is a continuously evolving project. This roadmap represents development goals, not guaranteed release dates or commitments.*
