# Publish BrainLoop to your GitHub account

This guide assumes the GitHub username **Uttamest**. The repository has **not** been created for you; follow these steps while logged into GitHub.

## 1. Create the repository

1. Open https://github.com/new.
2. Set **Owner** to `Uttamest`.
3. **Repository name:** `brainloop` (lowercase).
4. **Description:** `BrainLoop is a digital flashcard learning app designed to make active recall and spaced repetition simple, organized, and effective.`
5. **Visibility:** Public (recommended for free GitHub Pages).
6. **Do not add** an auto-generated README, .gitignore, or license now: this bundle already contains a README and .gitignore, and the license needs your choice.
7. Click **Create repository**.

## 2. Upload the files (no terminal required)

1. On the new empty repository page choose **uploading an existing file**, or choose **Add file → Upload files**.
2. Extract `brainloop-github-starter.zip` on your computer.
3. Open the extracted folder and select **all files inside it** (not the parent folder itself).
4. Make sure `index.html`, `app.js`, and `styles.css` are directly in the repository root. Also upload `README.md`, `ROADMAP.md`, `GITHUB_SETUP.md`, and `favicon.svg`.
5. Files beginning with a dot, like `.nojekyll` and `.gitignore`, may be hidden on some computers. Enable hidden files in your file browser or create them later through GitHub's web editor. The site works without `.gitignore`, and `.nojekyll` is optional for this plain HTML site.
6. Enter commit message: `Initial BrainLoop flashcard MVP`.
7. Click **Commit changes**.

## 3. Publish GitHub Pages

1. Open your new repository's **Settings** tab.
2. Select **Pages** in the left sidebar.
3. In **Build and deployment**, select **Deploy from a branch**.
4. Choose branch **main**, folder **/(root)**, then **Save**.
5. Once GitHub shows a successful deployment, open:

   https://uttamest.github.io/brainloop/

If you initially receive a 404 error, wait for the first deployment and look at **Actions** for any build errors.

## 4. Improve repository discoverability

On your repository home, open the ⚙ gear next to **About** and use:

- **Website:** `https://uttamest.github.io/brainloop/` (after it is live)
- **Topics:** `flashcards`, `spaced-repetition`, `active-recall`, `javascript`, `html`, `css`, `education`, `learning-app`, `github-pages`

Pin BrainLoop to your GitHub profile after publishing if you want recruiters to find it quickly.

## 5. Add BrainLoop to your portfolio

Once the site works, add these two verified links to your portfolio project card:

- **Source code:** https://github.com/Uttamest/brainloop
- **Live demo:** https://uttamest.github.io/brainloop/

Describe the live MVP as a **frontend browser app using localStorage**, not as an AI-powered, cloud-synced, or FastAPI service unless you've really built those features.

## Optional: upload from Git on your computer

For a newly created empty repository, run these commands from the folder containing `index.html`:

```bash
git init
git add .
git commit -m "Initial BrainLoop flashcard MVP"
git branch -M main
git remote add origin https://github.com/Uttamest/brainloop.git
git push -u origin main
```

You must be authenticated to GitHub for `git push` to work. If the repo already contains commits, do not force-push over them.

## Common mistakes

- **404:** Verify the repository name is `brainloop`, `index.html` is in the root, and Pages is configured to deploy from `main` and `/(root)`.
- **Page has no styling:** Make sure `styles.css` is alongside `index.html` and check exact capitalization.
- **Page buttons don't work:** Make sure `app.js` is alongside `index.html` and check your browser's Developer Tools console.
- **My flashcards disappeared:** This MVP saves in this browser's localStorage. Import your last JSON backup in Settings; there is no cloud recovery.
- **GitHub repository preview does not render:** GitHub's editor Preview shows file changes, not a live running site. Use GitHub Pages URL to view the app.
