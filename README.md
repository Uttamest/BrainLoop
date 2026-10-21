# 🧠 BrainLoop — Learn it. Keep it.

**An interactive flashcard website that helps learners practice active recall and review information over time.**

BrainLoop is a frontend-only study application built as an initial working MVP. It supports creating your own flashcards, organizing learning decks, and scheduling reviews with a simple tiered interval system.

**Live site (after GitHub Pages is enabled):** https://uttamest.github.io/brainloop/  
**Developer:** [Uttam Shrestha](https://github.com/Uttamest)

## ✨ Features implemented in this version

- **Custom decks:** Create, edit, browse, search, and delete flashcard decks.
- **Flashcards:** Add, edit, and delete questions and answers.
- **Study mode:** Reveal answers and rate your recall using **Again**, **Good**, or **Easy**.
- **Scheduled reviews:** Cards you remember are scheduled at increasing intervals (1, 3, 7, 14, 30, 60, or 120 days). An **Again** answer resets a card's level and offers one retry in the current session.
- **Progress dashboard:** View total cards, cards due, and mastered cards.
- **Local-first storage:** Data persists in the current browser with `localStorage`.
- **Data portability:** Export a JSON backup and import it again later.
- **Responsive design:** Desktop and mobile layouts, plus keyboard study shortcuts.

> **Note:** Two small sample decks are created on first use so visitors can try the experience. They are editable and removable.

## 🛠 Tech stack

- **HTML5** — semantic markup
- **CSS3** — responsive layout and visual design
- **Vanilla JavaScript** — navigation, forms, flashcards, and review scheduling
- **Browser localStorage** — data persistence on a single device/browser
- **GitHub Pages** — free static website hosting

This MVP **does not yet** include AI generation, accounts, cloud synchronization, or a server/database.

## 🚀 Run locally

No dependency installation is required.

1. Download or clone this repository.
2. Open `index.html` in a modern browser.
3. Create a deck, add cards, and click **Review** to study.

You can also use a simple local web server:

```bash
python -m http.server 5500
```

Then open http://localhost:5500.

## 🌍 Deploy on GitHub Pages

1. Create a public repository named `brainloop` in the GitHub account **Uttamest**.
2. Upload the files in this folder **directly to the repository root**. `index.html` must be at the root.
3. Go to **Settings → Pages**.
4. Under **Build and deployment**, choose **Deploy from a branch**.
5. Set branch to `main` and folder to `/(root)`; select **Save**.
6. After the deployment completes, visit **https://uttamest.github.io/brainloop/**.

The website URL above is expected **after** publishing. It is not a claim that the site is already live.

## 📁 Repository structure

```text
brainloop/
├── index.html        # Website shell and accessible navigation
├── styles.css        # Responsive styling and theme
├── app.js            # Decks, cards, reviews, backups, local persistence
├── favicon.svg       # Browser favicon
├── .gitignore        # Files that should not be committed
├── .nojekyll         # Serve the static files directly on Pages
├── README.md         # Project documentation
├── ROADMAP.md        # Potential future improvements
└── GITHUB_SETUP.md   # Step-by-step publishing guide
```

## 🧠 How scheduling works

Each card starts at **level 0** and is ready to study immediately.

| Rating | What happens |
| --- | --- |
| Again | Reset to level 0 and due now; the card can reappear once in the current session. |
| Good | Move up one level and schedule based on the interval table. |
| Easy | Move up two levels and schedule further into the future. |

Cards at **level 3+** count as mastered in the dashboard. This is a **basic review-interval approach**, not a validated adaptive memory model such as FSRS or SM-2.

## 🔒 Data and limitations

Your study data lives **only in the browser** in this version. Clearing site storage, using a private window, or switching devices can remove or hide your progress. Use **Settings → Export a backup** to keep a copy.

Importing a backup **replaces**, rather than merges with, the current data. The GitHub Pages deployment only hosts the frontend and cannot run a Python/FastAPI server.

## 🔭 Future ideas

See [ROADMAP.md](ROADMAP.md) for proposed features including AI-assisted flashcard creation, authenticated accounts, cloud sync, better scheduling, and richer study insights. These are **planned ideas, not implemented features**.

## 👨‍💻 Author

**Uttam Shrestha**  
GitHub: https://github.com/Uttamest  
Email: uttamnitro5@gmail.com  
LinkedIn: https://www.linkedin.com/in/mruttam/

## 📄 License

No open-source license has been selected for this starter repository. The repository owner may add a `LICENSE` file after deciding how others may use or redistribute the code.
