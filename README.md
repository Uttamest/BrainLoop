# 🧠 BrainLoop — Learn It. Keep It.

**A smarter way to learn, remember, and review.**

BrainLoop is a lightweight, browser-based flashcard application designed to help learners retain knowledge through **active recall and spaced repetition**.

Create your own flashcards, organize them into decks, track your learning progress, and review information at gradually increasing intervals. Built with HTML, CSS, and vanilla JavaScript, BrainLoop is a frontend-only MVP that runs directly in your browser without requiring an account or installation.

🌐 **Live Demo:** [https://uttamest.github.io/brainloop/](https://uttamest.github.io/brainloop/) *(Available after GitHub Pages deployment is completed.)*

👨‍💻 **Developer:** [Uttam Shrestha](https://github.com/Uttamest)

---

## ✨ Features

### 📚 Flashcard & Deck Management
- **Create custom decks:** Organize your learning materials by subject or topic.
- **Manage flashcards:** Create, edit, and delete questions and answers.
- **Search and browse:** Find decks and learning materials quickly.
- **Sample content:** Explore two sample decks included on first use. Both can be edited or deleted.

### 🧠 Interactive Study Mode
- **Active recall:** Test your memory before revealing each answer.
- **Self-assessment:** Rate your recall using three options: `Again`, `Good`, and `Easy`.
- **Retry support:** Cards rated `Again` are reset and can reappear once during the current study session.
- **Spaced review intervals:** Schedule future reviews using a tiered interval system.

### 📊 Learning Progress
- View your total number of flashcards.
- Track cards that are due for review.
- Monitor mastered cards and overall study progress.

### 💾 Local-First Data Storage
- Automatically save your study data using browser `localStorage`.
- Export your data as a JSON backup.
- Import a previously exported backup to restore your decks and cards.

### 📱 Responsive Experience
- Layouts designed for desktop and mobile screens.
- Keyboard shortcuts for supported study actions.
- No account registration or dependency installation required.

---

## 🛠️ Tech Stack

| Technology | Purpose |
|---|---|
| **HTML5** | Semantic structure and accessible navigation |
| **CSS3** | Responsive layouts, styling, and visual design |
| **Vanilla JavaScript** | Application logic, deck management, and review scheduling |
| **Browser localStorage** | Persistent storage of study data in the current browser |
| **GitHub Pages** | Static website hosting |

BrainLoop intentionally uses a simple frontend architecture, making the project lightweight, easy to run, and suitable for further development.

**Current scope:** This MVP does not include AI-powered flashcard generation, user accounts, cloud synchronization, or a backend server/database.

---

## 🚀 Getting Started

### Run Locally

No package installation or build process is required.

**1. Clone the repository**

```bash
git clone https://github.com/Uttamest/brainloop.git
```

**2. Open the project folder**

```bash
cd brainloop
```

**3. Launch the application**

Open `index.html` in a modern web browser.

You can now create a deck, add flashcards, and start studying using the **Review** feature.

### Run with a Local Web Server

Alternatively, use Python's built-in HTTP server.

From the project directory, run:

```bash
python -m http.server 5500
```

Then open the following address in your browser:

[http://localhost:5500](http://localhost:5500)

---

## 🌍 Deploy with GitHub Pages

Follow these steps to publish BrainLoop as a static website.

**1. Create a GitHub repository**

Create a public repository named `brainloop` under your GitHub account, [Uttamest](https://github.com/Uttamest). Skip this step if the repository already exists.

**2. Upload the project files**

Upload the project files directly to the repository's root directory. Ensure that `index.html` is located at the root.

**3. Open repository settings**

Navigate to **Settings → Pages**.

**4. Configure the deployment source**

Under **Build and deployment**, select:

- **Source:** Deploy from a branch
- **Branch:** `main`
- **Folder:** `/(root)`

Click **Save**.

**5. Wait for deployment to complete**

GitHub Pages will publish the static website after the deployment process finishes.

**6. Visit the website**

Your expected website address is:

[https://uttamest.github.io/brainloop/](https://uttamest.github.io/brainloop/)

*Note: This is the intended deployment URL, not confirmation that the website is currently live.*

For additional instructions, see [`GITHUB_SETUP.md`](GITHUB_SETUP.md).

---

## 📁 Project Structure

```text
brainloop/
├── index.html        # Main application structure
├── styles.css        # Styling, layout, and responsive design
├── app.js            # Application logic and study functionality
├── favicon.svg       # Browser tab icon
├── .gitignore        # Files excluded from version control
├── .nojekyll         # Disables Jekyll processing on GitHub Pages
├── README.md         # Project documentation
├── ROADMAP.md        # Planned improvements and future features
└── GITHUB_SETUP.md   # GitHub Pages deployment instructions
```

---

## 🧠 How Spaced Repetition Works

BrainLoop uses a basic, tiered review system to help learners revisit flashcards over progressively longer intervals.

Each card starts at **Level 0** and is ready for review immediately.

### Review Ratings

| Rating | Behavior |
|---|---|
| **Again** | Resets the card to Level 0, makes it due immediately, and allows one retry in the current session. |
| **Good** | Advances the card by one level and schedules its next review. |
| **Easy** | Advances the card by two levels and schedules a review further into the future. |

### Review Intervals

The application uses the following interval sequence:

**1 → 3 → 7 → 14 → 30 → 60 → 120 days**

Cards at **Level 3 or higher** count as mastered in the progress dashboard.

This is a deliberately simple, tiered scheduling approach intended for the initial MVP. It is **not a validated adaptive algorithm** such as SM-2 or FSRS.

---

## 🔒 Data Storage, Privacy & Limitations

BrainLoop stores study data locally in your browser using `localStorage`.

This means:

- Your study data is not automatically synchronized across devices.
- There is no user account or cloud database in the current version.
- Clearing browser storage or site data may permanently remove saved progress.
- Private browsing sessions may not retain data after the session ends.
- Switching browsers or devices does not automatically transfer your decks.

### Back Up Your Data

To protect your progress, use **Settings → Export a backup** to download a JSON copy of your study data.

When restoring data, be aware that **importing a backup replaces the current data rather than merging with it**. Export your current data before importing another backup if you need to preserve it.

### Hosting Limitation

GitHub Pages supports static website hosting. It does not run a Python/FastAPI backend or provide a database for this application. Backend functionality would require a separate hosting service.

---

## 🔭 Roadmap

BrainLoop is an initial working MVP with room to grow.

Potential future improvements include:

- **AI-assisted flashcard creation** from notes, documents, or study materials.
- **User authentication** and personalized study profiles.
- **Cloud synchronization** across multiple devices.
- **Improved scheduling algorithms** for more adaptive review sessions.
- **Study analytics** and more detailed learning insights.
- **Enhanced learning tools** and additional customization options.

See [`ROADMAP.md`](ROADMAP.md) for more details.

*These are proposed improvements, not features currently implemented in this version.*

---

## 👨‍💻 Author

**Uttam Shrestha**

Developer and creator of BrainLoop.

- **GitHub:** [github.com/Uttamest](https://github.com/Uttamest)
- **LinkedIn:** [linkedin.com/in/mruttam](https://www.linkedin.com/in/mruttam/)
- **Email:** [uttamnitro5@gmail.com](mailto:uttamnitro5@gmail.com)

---

## 📄 License

No open-source license has been selected for this repository yet.

Until a license is added, the project should not be assumed to grant permission to reuse, modify, or redistribute its code. A `LICENSE` file can be added once the repository owner decides the terms under which the project will be shared.

---

**BrainLoop — Learn it. Keep it.**

Build better study habits, one flashcard at a time.
