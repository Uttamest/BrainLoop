# Publish BrainLoop on GitHub

This guide walks you through publishing **BrainLoop** to your GitHub account and deploying it as a live website using **GitHub Pages**.

You do not need prior Git experience. The recommended method uses GitHub's website, so you can publish the project without using a terminal.

**GitHub account:** `Uttamest`  
**Repository:** `brainloop`  
**Live website:** https://uttamest.github.io/brainloop/  
**Source code:** https://github.com/Uttamest/brainloop

> **Before you begin:** Make sure you have the `brainloop-github-starter.zip` file downloaded and that you can sign in to your GitHub account.

---

## 1. Create a GitHub repository

First, create an empty repository for BrainLoop.

1. Visit [GitHub — Create a new repository](https://github.com/new).
2. Set **Owner** to `Uttamest`.
3. Enter the following repository details:

   | Setting | Value |
   |---|---|
   | Repository name | `brainloop` |
   | Description | A digital flashcard learning app built around active recall and spaced repetition. |
   | Visibility | Public |

4. Leave the following options unchecked:
   - Add a README file
   - Add `.gitignore`
   - Choose a license

   The starter package already includes project documentation and a `.gitignore` file. You can choose and add a license separately when you have decided which one suits the project.

5. Click **Create repository**.

Your repository should now be available at:

https://github.com/Uttamest/brainloop

---

## 2. Upload the BrainLoop project files

The easiest way to upload BrainLoop is directly through GitHub.

1. Extract `brainloop-github-starter.zip` on your computer.
2. Open the extracted folder.
3. Open the folder containing the actual project files.
4. Select **all files and folders inside it**. Do not upload the ZIP file itself or just the parent folder.
5. Return to your newly created GitHub repository.
6. Select **Add file → Upload files**, or click the existing-repository prompt to upload files.
7. Drag and drop the selected project files into the upload area.

### Check the repository structure

Make sure the main application files are located directly in the repository's root directory.

The repository should look similar to this:

```text
brainloop/
│
├── index.html
├── styles.css
├── app.js
├── favicon.svg
│
├── README.md
├── ROADMAP.md
├── GITHUB_SETUP.md
│
├── .gitignore
└── .nojekyll
```

The exact contents may differ slightly depending on the starter package.

**Important:**

- `index.html`, `styles.css`, and `app.js` must be in the repository root, alongside one another.
- Do not accidentally upload an extra nested `brainloop` folder.
- Files beginning with a dot, such as `.gitignore` and `.nojekyll`, may be hidden by your operating system. Enable hidden files in File Explorer if necessary.
- `.gitignore` is useful for future local development but is not required for GitHub Pages deployment.
- `.nojekyll` is optional for this basic HTML, CSS, and JavaScript application.

### Commit the uploaded files

At the bottom of the upload page:

1. Enter the commit message:

   `Initial BrainLoop flashcard MVP`

2. Keep the option to commit directly to the `main` branch selected.
3. Click **Commit changes**.

Your project files should now appear in the repository.

---

## 3. Enable GitHub Pages

GitHub Pages will host your BrainLoop application as a publicly accessible website.

1. Open the `brainloop` repository.
2. Click the **Settings** tab.
3. Select **Pages** from the left sidebar.
4. Find the **Build and deployment** section.
5. Under **Source**, select:

   `Deploy from a branch`

6. Configure the deployment as follows:

   | Setting | Selection |
   |---|---|
   | Branch | `main` |
   | Folder | `/(root)` |

7. Click **Save**.

GitHub will begin building and publishing your website.

### Open your live application

After the deployment completes successfully, visit:

**https://uttamest.github.io/brainloop/**

The first deployment may take a little time. If the page initially displays a 404 error, check the repository's **Actions** tab for deployment progress or errors before making changes.

---

## 4. Improve repository presentation and discoverability

A well-organized repository makes BrainLoop easier to understand and present to other developers, collaborators, and potential recruiters.

### Configure the About section

1. Return to the repository's main page.
2. Find the **About** section.
3. Click its settings gear icon.
4. Add the following information:

   **Website**

   `https://uttamest.github.io/brainloop/`

   **Description**

   `A digital flashcard learning app designed around active recall, spaced repetition, and progress tracking.`

   **Topics**

   ```text
   flashcards
   spaced-repetition
   active-recall
   javascript
   html
   css
   education
   learning-app
   github-pages
   ```

5. Save your changes.

### Pin BrainLoop to your profile

Once the repository is published:

1. Open your GitHub profile.
2. Select **Customize your pins**.
3. Find `brainloop`.
4. Add it to your pinned repositories.

This makes the project easier to discover when someone visits your GitHub profile.

---

## 5. Add BrainLoop to your portfolio

After confirming that the live application works correctly, include BrainLoop in your portfolio's Projects section.

Use these project links:

| Link | URL |
|---|---|
| Source code | https://github.com/Uttamest/brainloop |
| Live demo | https://uttamest.github.io/brainloop/ |

### Recommended project description

**BrainLoop — Interactive Flashcard Learning App**

A browser-based flashcard learning application designed to make studying more organized and effective through active recall and spaced repetition.

**Key features:**
- Create, edit, search, and organize custom flashcard decks.
- Review questions using interactive flip cards.
- Schedule reviews based on learning performance.
- Track study progress and review history.
- Import and export flashcard data using JSON.
- Save study data locally in the browser.
- Responsive interface with light and dark themes.

**Technologies:** HTML, CSS, JavaScript, Browser LocalStorage.

> **Be accurate when presenting the project:** The current MVP is a frontend browser application that uses `localStorage` for data persistence. Do not describe it as AI-powered, cloud-synchronized, or backed by FastAPI or a remote database unless those capabilities have actually been implemented.

---

## 6. Optional: Upload using Git

For developers who prefer working with the terminal, Git provides a more convenient way to upload future changes.

Open a terminal in the project directory containing `index.html` and run:

```bash
git init
git add .
git commit -m "Initial BrainLoop flashcard MVP"
git branch -M main
git remote add origin https://github.com/Uttamest/brainloop.git
git push -u origin main
```

Make sure you are authenticated with GitHub before running `git push`.

**Important:** These commands assume that the remote repository is empty and that you have not already configured a Git remote in this project. If a remote already exists, check it with `git remote -v` rather than adding another one. Never force-push over existing repository history just to complete the initial upload.

### Publishing future updates

After making changes to your application, use:

```bash
git add .
git commit -m "Improve BrainLoop interface"
git push
```

GitHub Pages will automatically deploy the updated version when the configured branch is updated.

---

## 7. Troubleshooting

Here are solutions to common issues you may encounter during publishing.

| Problem | Possible cause and solution |
|---|---|
| **404 — Page not found** | Confirm the repository is named `brainloop`, `index.html` is in the root, and Pages is configured to deploy from `main` and `/(root)`. Check the Actions tab for deployment errors. |
| **Website has no styling** | Ensure `styles.css` is in the same directory as `index.html`. Check the filename's capitalization and the stylesheet path in the HTML. |
| **Buttons or interactions do not work** | Confirm `app.js` is uploaded correctly and that its script path is correct. Open your browser's Developer Tools and inspect the Console for JavaScript errors. |
| **Flashcards disappear** | BrainLoop currently stores data in the browser's `localStorage`. Data is not automatically synchronized across devices or browsers. Import a previously exported JSON backup when needed. |
| **Changes are not appearing** | Check whether the latest commit has been deployed successfully. Refresh the website and try a hard refresh with `Ctrl + Shift + R`. |
| **GitHub Preview does not run the app** | The repository's file editor preview is not a live hosting environment. Open the GitHub Pages URL to test the actual application. |
| **Hidden files are missing** | Enable hidden files in your file manager and upload them separately. Remember that `.gitignore` and `.nojekyll` are not essential for this basic deployment. |

---

## 8. Final publishing checklist

Before considering the publication complete, verify each item.

- [ ] The public GitHub repository `Uttamest/brainloop` exists.
- [ ] All project files are uploaded to the repository root.
- [ ] `index.html`, `styles.css`, and `app.js` are present.
- [ ] The repository contains the README and supporting documentation.
- [ ] GitHub Pages is configured to deploy from `main` and `/(root)`.
- [ ] The deployment has completed successfully.
- [ ] The live website opens without errors.
- [ ] Flashcard creation, editing, deletion, and study interactions work.
- [ ] JSON import and export work correctly.
- [ ] The website displays properly on desktop and mobile.
- [ ] The repository's About section contains the correct description and website link.
- [ ] BrainLoop is pinned to your GitHub profile.

---

## Published project

Once everything is working, your BrainLoop project will have two primary destinations:

**GitHub Repository**  
https://github.com/Uttamest/brainloop

**Live Application**  
https://uttamest.github.io/brainloop/

**BrainLoop — Learn it. Keep it.**

A practical learning tool built to make studying more focused, organized, and effective.
