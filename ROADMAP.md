# BrainLoop Roadmap

This file describes **ideas for future work**, not completed functionality. Adjust the order as the product develops.

## Phase 1 — Working browser MVP ✅

- [x] Create, edit, and delete decks
- [x] Create, edit, and delete flashcards
- [x] Study cards with flip and self-rating
- [x] Basic interval-based review scheduling
- [x] Browser-local progress tracking
- [x] Export and import JSON backups
- [x] Responsive web interface

## Phase 2 — Better everyday studying (proposed)

- [ ] Deck import from CSV
- [ ] Rich text, images, and code blocks in cards
- [ ] Review history charts and optional study reminders
- [ ] Accessible dialog focus trapping and more automated accessibility tests
- [ ] Improved review scheduler (e.g. FSRS) and configurable goals
- [ ] Automated UI and unit tests

## Phase 3 — Accounts and synchronization (proposed)

- [ ] User authentication
- [ ] Backend API and database (technology to be chosen)
- [ ] Cloud-backed decks and review history
- [ ] Sync between browsers and devices
- [ ] Safe import/export and account deletion flows

## Phase 4 — AI-assisted learning (proposed)

- [ ] Generate draft flashcards from pasted study notes
- [ ] Suggest improvements to ambiguous questions
- [ ] Review or edit AI-generated cards before saving
- [ ] Server-side API key handling, usage limits, and privacy safeguards

> **Never embed a private AI API key directly in browser JavaScript or publish secrets in GitHub.** GitHub Pages is static hosting; AI features involving secret keys would need a secure backend.
