# Advanced WooCommerce Discount Code Generator 🛒

<p align="center">
  <strong>Advanced discount rules and coupon management for WooCommerce</strong>
</p>

<p align="center">
  <a href="https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases/latest">
    <img src="https://img.shields.io/github/v/release/alirezasayadi/woocommerce-advanced-discount-builder?label=Latest%20Release" alt="Latest Release">
  </a>
  <a href="https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases">
    <img src="https://img.shields.io/github/downloads/alirezasayadi/woocommerce-advanced-discount-builder/total?label=Downloads" alt="Downloads">
  </a>
  <a href="https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases">
    <img src="https://img.shields.io/github/release-date/alirezasayadi/woocommerce-advanced-discount-builder?label=Released" alt="Release Date">
  </a>
  <a href="https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/actions">
    <img src="https://img.shields.io/github/actions/workflow/status/alirezasayadi/woocommerce-advanced-discount-builder/test-and-release.yml?label=Build%20%26%20Tests" alt="Build & Tests">
  </a>
  <a href="https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/blob/main/LICENSE">
    <img src="https://img.shields.io/github/license/alirezasayadi/woocommerce-advanced-discount-builder" alt="License">
  </a>
</p>

<p align="center">
  <a href="README.fa.md">🇮🇷 راهنمای فارسی</a>
  ·
  <a href="https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases/latest">⬇️ Download</a>
  ·
  <a href="https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases">📦 Releases</a>
  ·
  <a href="https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/actions">⚙️ Actions</a>
</p>

---

## 📖 About

**Advanced WooCommerce Discount Code Generator** is a WordPress/WooCommerce plugin for creating flexible and powerful discount coupons using a rule-based discount system.

The plugin extends the standard WooCommerce coupon system and allows store administrators to create multiple discount rules within a single coupon.

Discount rules can target specific products, product categories, product brands, and other eligible products.

---

## 🚀 Download

### Latest Release

**[⬇️ Download the latest version](https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases/latest)**

The plugin is distributed through **GitHub Releases**.

Download the ZIP package from the latest release and install it directly through the WordPress administration panel.

### Current Version

| Component                 |  Version | Status   |
| ------------------------- | -------: | -------- |
| Advanced Discount Builder | `v1.0.0` | ✅ Stable |

> The downloadable plugin ZIP files are provided through GitHub Releases and are not stored directly in the repository.

---

## ✨ Features

* 🏷️ Create advanced discount coupons for WooCommerce
* 🎯 Apply discounts to specific products
* 📂 Apply discounts to entire product categories
* 🏷️ Apply discounts to specific product brands
* ⚙️ Create multiple discount rules within a single coupon
* 💰 Set different discount types and values for each rule
* 📊 Support percentage-based discounts
* 💵 Support fixed-amount discounts
* 🔄 Apply discounts to other eligible products
* 🚚 Configure free shipping for individual coupons
* 🛠️ Manage coupon rules directly from the WordPress admin panel
* 🔎 Search and select products, categories, and brands when creating rules
* 🧮 Automatically calculate and apply discounts in the cart and checkout
* 💳 Display original and discounted prices to customers
* 🛒 Display discount information in the cart and checkout
* 📦 Preserve discount information in WooCommerce orders
* 🧾 Display discount information in customer invoices and emails
* 🎟️ Support multiple coupons and discount rules
* 🔧 Includes tools for correcting discount data in existing orders

---

## 🧠 How It Works

The plugin extends the standard WooCommerce coupon system with a flexible rule-based discount engine.

Each coupon can contain multiple rules that determine:

* Which products are affected
* Which categories are affected
* Which brands are affected
* What type of discount is applied
* What discount value is used
* Whether other eligible products should receive a discount
* Whether free shipping should be enabled

### Example

A single coupon can be configured to:

* 🟢 Give **20% off** selected products
* 🔵 Give **10% off** an entire product category
* 🟣 Give a **fixed discount** on products from a specific brand
* 🟠 Apply a different discount to other eligible products
* 🚚 Provide **free shipping**

All coupon rules can be created, edited, and managed directly from the WordPress administration panel.

---

## 📦 Installation

### Method 1 — GitHub Release

1. Go to the [Latest Release](https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases/latest).
2. Download the plugin ZIP file.
3. Open your WordPress dashboard.
4. Go to **Plugins → Add New → Upload Plugin**.
5. Select the downloaded ZIP file.
6. Install the plugin.
7. Activate the plugin.

Make sure **WooCommerce** is installed and activated before using the plugin.

### Method 2 — Manual Installation

1. Download the latest release ZIP.
2. Extract the ZIP file.
3. Upload the plugin folder to:

```text
/wp-content/plugins/
```

4. Go to **WordPress Dashboard → Plugins**.
5. Find **Advanced WooCommerce Discount Code Generator**.
6. Click **Activate**.

---

## 🚀 Release Management

WPForge is not used for this project. The **Advanced WooCommerce Discount Code Generator** project includes its own release scripts for automating the Git release process.

The project provides two release scripts:

```text
scripts/
├── release.bat
└── release.sh
```

* `release.bat` — for Windows
* `release.sh` — for Linux and macOS

Both scripts perform the same release workflow and are designed to keep the release process consistent across operating systems.

---

## 📋 Release Requirements

Before creating a release, make sure:

1. Git is installed and available in `PATH`.
2. You are inside the project directory.
3. The project is a Git repository.
4. The `origin` remote is configured.
5. The correct branch is checked out.
6. Your changes are ready to be committed.
7. The project is in a state that is ready for release.
8. The version number follows Semantic Versioning.

The recommended default branch is:

```text
main
```

The release scripts will warn you if you are working on a different branch.

---

## 🪟 Windows Release

Windows users should use:

```text
scripts\release.bat
```

### 1. Open Command Prompt

Open **Command Prompt (CMD)** and navigate to the project directory.

For example:

```bat
cd D:\woocommerce-advanced-discount-builder
```

You can use another path if your project is located elsewhere.

### 2. Run the release script

```bat
scripts\release.bat
```

The script will guide you through the release process.

It will ask for:

```text
Commit message:
Version:
```

For example:

```text
Commit message: Release v1.0.1
Version: 1.0.1
```

The script also accepts a version with the `v` prefix:

```text
v1.0.1
```

The prefix is automatically removed and recreated correctly as:

```text
v1.0.1
```

### 3. Branch Check

The script checks the current Git branch.

If you are not on:

```text
main
```

you will receive a warning:

```text
[WARNING] You are not on the main branch.
```

You can choose whether to continue.

### 4. Remote Check

The script verifies that the Git remote named:

```text
origin
```

exists.

It also displays the configured remote URL before creating the release.

### 5. Release Summary

Before making any changes, the script displays a release summary containing:

```text
Branch
Version
Commit message
Remote
```

You must confirm the release before the Git operations begin.

---

## 🐧 Linux / 🍎 macOS Release

Linux and macOS users should use:

```text
scripts/release.sh
```

### 1. Open Terminal

Navigate to the project directory:

```bash
cd /path/to/woocommerce-advanced-discount-builder
```

For example:

```bash
cd ~/woocommerce-advanced-discount-builder
```

### 2. Make the script executable

You only need to do this once:

```bash
chmod +x scripts/release.sh
```

### 3. Run the release script

```bash
./scripts/release.sh
```

The script will ask for:

```text
Commit message:
Version:
```

For example:

```text
Commit message: Release v1.0.1
Version: 1.0.1
```

You can also enter:

```text
v1.0.1
```

The script automatically handles the `v` prefix.

---

## 🔢 Versioning

Releases use **Semantic Versioning**:

```text
MAJOR.MINOR.PATCH
```

Examples:

```text
1.0.0
1.0.1
1.1.0
2.0.0
```

The corresponding Git Tags use a `v` prefix:

```text
v1.0.0
v1.0.1
v1.1.0
v2.0.0
```

### Bug Fix

For backward-compatible bug fixes:

```text
1.0.0 → 1.0.1
```

### New Feature

For new backward-compatible features:

```text
1.0.0 → 1.1.0
```

### Breaking Change

For incompatible or breaking changes:

```text
1.0.0 → 2.0.0
```

Only versions matching the following format are accepted:

```text
X.Y.Z
```

For example:

```text
1.2.3
```

Invalid examples:

```text
1.2
1.2.3.4
version-1.0.0
1.0
```

---

## 🔍 Tag Validation

Before creating a release, both scripts check whether the requested version already exists.

The scripts check:

### Local Git Tags

```text
v1.0.0
```

### Remote Git Tags

```text
v1.0.0
```

If the tag already exists locally or on GitHub, the release process stops.

This prevents accidentally overwriting an existing release.

If a tag already exists on GitHub and you intentionally need to remove it, you can use:

```bash
git push origin --delete v1.0.0
```

Then recreate the release only when it is safe to do so.

---

## 🔄 Release Process

After confirmation, the release scripts perform the following operations.

### Step 1 — Add Files

All changes are staged:

```bash
git add .
```

### Step 2 — Create Commit

If there are staged changes, the script creates a commit using the commit message you entered.

For example:

```text
Release v1.0.1
```

If there are no new changes, the script continues without creating an additional commit.

### Step 3 — Push Branch

The current branch is pushed to the `origin` remote.

For the standard configuration:

```bash
git push origin main
```

### Step 4 — Create Release Tag

An annotated Git Tag is created:

```text
v1.0.1
```

with the message:

```text
Release v1.0.1
```

### Step 5 — Push Tag

The new tag is pushed to GitHub:

```bash
git push origin v1.0.1
```

### Step 6 — Trigger GitHub Actions

Once the tag reaches GitHub, the release workflow is triggered automatically.

The release script itself does **not** build the plugin or create the GitHub Release directly.

GitHub Actions handles the remaining release process.

---

## 🤖 GitHub Actions Release Workflow

After the version tag is pushed, GitHub Actions automatically starts the release workflow.

The workflow is responsible for tasks such as:

1. Running PHP syntax checks
2. Running WordPress tests
3. Installing WordPress
4. Installing WooCommerce
5. Activating the plugin
6. Running runtime checks
7. Building the plugin ZIP package
8. Creating the GitHub Release
9. Uploading the ZIP file as a Release Asset

You can monitor the workflow from:

**GitHub → Actions**

Or directly from:

[GitHub Actions](https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/actions)

---

## 📦 Release Assets

After a successful release workflow, the GitHub Release will contain the generated plugin package.

The main downloadable file is a ZIP package containing the plugin.

For example:

```text
woocommerce-advanced-discount-builder-v1.0.0.zip
```

The exact filename may depend on the configuration of the GitHub Actions workflow.

Users can download the ZIP from:

**GitHub → Releases → Latest Release**

and install it through:

**WordPress Dashboard → Plugins → Add New → Upload Plugin**

---

## ⚠️ Important Release Rules

### Do Not Create the GitHub Release Manually

When using the release scripts, you should **not manually create the GitHub Release**.

The process is automated:

```text
release.bat / release.sh
        ↓
Git commit
        ↓
Push branch
        ↓
Create Git tag
        ↓
Push tag
        ↓
GitHub Actions
        ↓
Run tests
        ↓
Build plugin ZIP
        ↓
Create GitHub Release
        ↓
Upload ZIP
```

You only need to run the release script and confirm the release.

---

## ❌ If a Release Fails

If GitHub Actions fails:

1. Open the GitHub repository.
2. Go to **Actions**.
3. Open the failed workflow.
4. Select the failed job.
5. Review the error logs.
6. Fix the problem locally.
7. Commit the fix.
8. Create a new appropriate version.

For example, if:

```text
v1.0.0
```

has already been published and a bug is fixed, use:

```text
v1.0.1
```

Do not attempt to create another release using the same version unless you intentionally remove and recreate the existing Git tag and release.

---

## 🔁 Recommended Release Workflow

The recommended development and release process is:

```text
1. Make changes
       ↓
2. Test locally
       ↓
3. Verify the plugin
       ↓
4. Run release.bat / release.sh
       ↓
5. Enter commit message
       ↓
6. Enter version
       ↓
7. Confirm release
       ↓
8. Git commit
       ↓
9. Push branch
       ↓
10. Create Git tag
       ↓
11. Push Git tag
       ↓
12. GitHub Actions starts
       ↓
13. Run automated tests
       ↓
14. Build plugin ZIP
       ↓
15. Create GitHub Release
       ↓
16. Upload ZIP
```

This provides a consistent and automated release process for Windows, Linux, and macOS.

---

## 🧪 Release Checklist

Before creating a new release, verify the following:

* [ ] All changes are complete.
* [ ] The plugin works correctly.
* [ ] WooCommerce compatibility has been checked.
* [ ] Tests pass locally where applicable.
* [ ] The correct Git branch is selected.
* [ ] The commit message is clear.
* [ ] The version follows `MAJOR.MINOR.PATCH`.
* [ ] The version has not already been released.
* [ ] The Git remote `origin` is configured.
* [ ] The release summary has been reviewed.
* [ ] The release has been confirmed.
* [ ] GitHub Actions completes successfully.
* [ ] The generated ZIP is available in GitHub Releases.

---

## 🛠️ Release Scripts

The project contains:

```text
scripts/
├── release.bat
└── release.sh
```

### Windows

```bat
scripts\release.bat
```

### Linux / macOS

```bash
chmod +x scripts/release.sh
./scripts/release.sh
```

Both scripts are designed to provide the same release workflow while following the conventions of their respective operating systems.

---

## 🔗 Release Links

### Latest Release

[Download the latest version](https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases/latest)

### All Releases

[View all releases](https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/releases)

### GitHub Actions

[View GitHub Actions](https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/actions)

---
