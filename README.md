
# 🧠 BrainLoop — Learn It. Keep It.

**A simple, smarter way to study, remember, and review.**

BrainLoop is a lightweight, browser-based flashcard app that helps learners build stronger study habits through **active recall** and **tiered spaced repetition**.

Create decks, practice with interactive flashcards, and keep track of what to review next—all without creating an account or installing software.

Built with **HTML, CSS, and vanilla JavaScript**, BrainLoop is a frontend-only minimum viable product (MVP) that runs in a modern web browser.

**[🌐 Live Demo (after deployment)](https://uttamest.github.io/brainloop/)** · **[🚀 Deployment Guide](GITHUB_SETUP.md)** · **[🔭 Roadmap](ROADMAP.md)**

> **Deployment note:** The demo link is the expected GitHub Pages URL. It should not be treated as confirmation that the site is currently live.

---

## ✨ Key Features

| Area | What BrainLoop offers |
| --- | --- |
| **Deck organization** | Create custom decks, browse your collection, and search for learning material. |
| **Flashcard management** | Add, edit, and delete question-and-answer cards. |
| **Interactive study** | Reveal answers only after trying to recall them; then rate your memory as **Again**, **Good**, or **Easy**. |
| **Spaced review** | Automatically schedule future reviews using progressively longer intervals. |
| **Progress tracking** | See total cards, cards due for review, and cards counted as mastered. |
| **Local-first storage** | Save study data in your browser with `localStorage`; export and import JSON backups. |
| **Responsive interface** | Study on desktop or mobile, with keyboard shortcuts for supported actions. |

BrainLoop includes **two editable sample decks** on first use to help new users get started.

---

## 🚀 Getting Started

### Option 1: Open the app directly

No package manager, dependencies, or build step is required.

```bash
git clone https://github.com/Uttamest/brainloop.git
cd brainloop
```

Open `index.html` in a modern web browser.

### Option 2: Run a local web server

From the project directory, run:

```bash
python -m http.server 5500
```

Then visit **http://localhost:5500**.

### Your First Study Session

1. **Create a deck** for a subject or topic, or explore a sample deck.
2. **Add flashcards** with a question on the front and an answer on the back.
3. **Start a review**, think of the answer, and reveal the card.
4. **Rate your recall** using **Again**, **Good**, or **Easy**.
5. **Return to review** cards when they become due.

---

## 🧠 How Spaced Repetition Works

BrainLoop uses a straightforward, level-based review scheduler.

Every new card begins at **Level 0** and is available for immediate review.

### Review Ratings

| Rating | What happens |
| --- | --- |
| **Again** | Resets the card to Level 0, makes it due immediately, and permits one retry within the current study session. |
| **Good** | Advances the card by one level and schedules a future review. |
| **Easy** | Advances the card by two levels and schedules a later review. |

### Review Intervals

The scheduler uses these review intervals:

**1 → 3 → 7 → 14 → 30 → 60 → 120 days**

Cards at **Level 3 or above** are counted as *mastered* in the progress dashboard.

> **Important:** This is a simple tiered scheduling system for the MVP, **not** a validated adaptive algorithm such as SM-2 or FSRS.

---

## 👥 Project Team & Proposed Task Allocation

BrainLoop is presented here as a collaborative project developed by a four-member team.

The assignments below are **proposed responsibilities** for organizing teamwork, not a verified record of completed individual contributions.

### 1. SHRESTHA UTTAM
**Role: Project Lead & Core Developer**

**Responsibilities:**
- Plan the project scope, goals, and development milestones.
- Organize the overall application architecture.
- Develop the core JavaScript application logic.
- Implement interactive flashcard study sessions.
- Develop the tiered spaced-repetition scheduling system.
- Integrate features developed by team members.
- Manage the GitHub repository and project integration.
- Configure and manage GitHub Pages deployment.

**Primary Focus:** Project management, core functionality, feature integration, and deployment.

### 2. THAPA MANISHA
**Role: UI/UX Designer & Frontend Developer**

**Responsibilities:**
- Design the overall user interface and experience.
- Create visually organized deck and flashcard layouts.
- Develop responsive styling using CSS3.
- Design interactive buttons, cards, forms, and navigation.
- Improve usability, readability, and visual consistency.
- Optimize the interface for desktop and mobile devices.
- Improve accessibility and user interaction feedback.
- Collaborate with the core developer to integrate frontend designs.

**Primary Focus:** User interface, user experience, responsive design, and frontend styling.

### 3. B K SURAJ
**Role: Feature Developer & Data Management**

**Responsibilities:**
- Develop flashcard creation, editing, and deletion features.
- Implement deck creation and management functionality.
- Develop search and browsing functionality.
- Implement browser `localStorage` integration.
- Manage flashcard data persistence and retrieval.
- Implement JSON backup export and import features.
- Validate flashcard and deck data handling.
- Test data management features and resolve functional issues.

**Primary Focus:** Flashcard management, browser storage, and data-handling functionality.

### 4. KHAND THAKURI SHRADDHA
**Role: Quality Assurance, Testing & Documentation**

**Responsibilities:**
- Prepare and review sample flashcard content.
- Test deck creation, editing, and deletion workflows.
- Test flashcard review sessions and rating functionality.
- Verify spaced-repetition scheduling behavior.
- Test backup export and restoration processes.
- Check application behavior on different screen sizes.
- Identify bugs and document testing results.
- Maintain project documentation and user instructions.
- Assist with final project verification before deployment.

**Primary Focus:** Testing, quality assurance, sample content, and documentation.

### Team Responsibilities Summary

| Team Member | Assigned Role | Main Focus |
| --- | --- | --- |
| **SHRESTHA UTTAM** | Project Lead & Core Developer | Architecture, study logic, scheduling, integration, deployment |
| **THAPA MANISHA** | UI/UX & Frontend Developer | UI design, CSS, responsiveness, accessibility |
| **B K SURAJ** | Feature & Data Developer | Deck management, flashcards, search, localStorage, backups |
| **KHAND THAKURI SHRADDHA** | QA & Documentation | Testing, bug reporting, sample content, documentation |

### 🤝 Team Collaboration Workflow

To maintain an organized development process, the team follows this proposed workflow:

1. **Planning:** Discuss requirements, define features, and assign responsibilities.
2. **Development:** Implement assigned tasks using separate Git branches or clearly assigned files.
3. **Code Review:** Review changes and test functionality before merging into the main branch.
4. **Testing:** Verify flashcards, review scheduling, saved data, and responsive layouts.
5. **Deployment:** Publish the reviewed application through GitHub Pages.

---

## 🛠️ Tech Stack

| Technology | Purpose |
| --- | --- |
| **HTML5** | Application structure and semantic markup |
| **CSS3** | Layout, styling, and responsive design |
| **Vanilla JavaScript** | App logic, flashcard interactions, and review scheduling |
| **Browser `localStorage`** | Device/browser-local persistence |
| **GitHub Pages** | Static website hosting |

### Application Architecture

BrainLoop uses a lightweight frontend-only architecture.

**Current MVP:**
- No backend server.
- No cloud database.
- No user authentication.
- No external framework dependency.
- No AI-powered flashcard generation.
- No cross-device synchronization.

This architecture allows the application to run as a static website without requiring server-side infrastructure.

---

## 📁 Project Structure

```text
brainloop/
├── index.html       # Main application markup
├── styles.css       # Design and responsive styling
├── app.js           # Flashcards, study sessions, and data logic
├── favicon.svg      # Browser tab icon
├── .gitignore       # Git ignore rules
├── .nojekyll        # Disable Jekyll processing on GitHub Pages
├── README.md        # Project overview and setup
├── ROADMAP.md       # Planned enhancements
└── GITHUB_SETUP.md  # Deployment instructions
```

---

## 💾 Storage, Backups & Privacy

BrainLoop stores study data using your browser's **`localStorage`**.

In the current MVP:

- Data is **not synchronized** between browsers or devices.
- Clearing the site's browser storage can **permanently delete** saved decks and progress.
- Private/incognito sessions may not retain data after the session ends.
- Changing devices or browsers requires **manual backup and restoration**.

### Export a Backup

Use **Settings → Export a backup** to save a JSON copy of your data.

### Restore a Backup

Use the app's import feature to load a previously exported JSON file.

> **Warning:** Importing a backup **replaces** existing app data; it does not merge decks. Export your current data before importing another backup.

---

## 🌍 Deploy to GitHub Pages

Follow these steps to publish BrainLoop online.

### Step 1: Create a Repository

Create a public repository named **`brainloop`** under the GitHub account [Uttamest](https://github.com/Uttamest), if one does not already exist.

### Step 2: Upload Project Files

Upload the application files to the repository.

Ensure that **`index.html` is located at the repository root**.

### Step 3: Open GitHub Pages Settings

Navigate to:

**Repository → Settings → Pages**

### Step 4: Configure Deployment

Under **Build and deployment**, select:

- **Source:** Deploy from a branch
- **Branch:** `main`
- **Folder:** `/(root)`

Click **Save**.

### Step 5: Verify Deployment

Once GitHub Pages finishes deploying the project, the expected website URL is:

**https://uttamest.github.io/brainloop/**

For more details, see **[GITHUB_SETUP.md](GITHUB_SETUP.md)**.

> GitHub Pages hosts static files. It does not run a Python/FastAPI backend or host an application database; those would require separate services.

---

## 🔭 Future Roadmap

BrainLoop is an initial MVP designed with opportunities for future development.

### Potential Improvements

- **AI-assisted flashcard generation:** Create flashcards from notes, documents, and study materials.
- **User authentication:** Support personalized user accounts and study profiles.
- **Cloud synchronization:** Access flashcards and progress from multiple devices.
- **Advanced spaced repetition:** Explore adaptive scheduling methods.
- **Learning analytics:** Provide more detailed statistics and study performance insights.
- **Study customization:** Introduce additional learning tools and personalization options.

These are **planned ideas, not implemented features**.

See **[ROADMAP.md](ROADMAP.md)** for the longer-term plan.

---

## 📬 Project Contact

### SHRESTHA UTTAM

**Project Creator & Repository Contact**

- **GitHub:** [github.com/Uttamest](https://github.com/Uttamest)
- **LinkedIn:** [linkedin.com/in/mruttam](https://www.linkedin.com/in/mruttam/)
- **Email:** [uttamnitro5@gmail.com](mailto:uttamnitro5@gmail.com)

### Project Team

- **SHRESTHA UTTAM**
- **THAPA MANISHA**
- **B K SURAJ**
- **KHAND THAKURI SHRADDHA**

---

## 📄 License

No open-source license has been selected for this repository.

Until a license is added, do not assume permission to reuse, modify, or redistribute the project's code.

A `LICENSE` file can be added once the repository owner chooses the terms.

---

<p align="center">
  <strong>🧠 BrainLoop — Learn It. Keep It.</strong>
  <br>
  Build better study habits, one flashcard at a time.
</p>
