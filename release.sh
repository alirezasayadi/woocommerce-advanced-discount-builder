#!/usr/bin/env bash

set -euo pipefail

# ============================================================
# Advanced WooCommerce Discount Code Generator
# Release Script
# ============================================================

PLUGIN_NAME="woocommerce-advanced-discount-builder"
DEFAULT_BRANCH="main"

echo
echo "================================================"
echo " Advanced WooCommerce Discount Code Generator"
echo " Release Tool"
echo "================================================"
echo

# ============================================================
# Check Git
# ============================================================

if ! command -v git >/dev/null 2>&1; then
    echo "❌ Error: Git is not installed."
    exit 1
fi

# ============================================================
# Check Git repository
# ============================================================

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "❌ Error: This directory is not a Git repository."
    echo
    echo "Run this script inside your project directory."
    exit 1
fi

# ============================================================
# Current branch
# ============================================================

BRANCH="$(git branch --show-current)"

echo "📌 Current branch: ${BRANCH}"
echo

if [[ "${BRANCH}" != "${DEFAULT_BRANCH}" ]]; then
    echo "⚠️ Warning: You are not on '${DEFAULT_BRANCH}'."
    echo

    read -r -p "Continue anyway? [y/N]: " CONFIRM

    if [[ ! "${CONFIRM}" =~ ^[Yy]$ ]]; then
        echo "Cancelled."
        exit 0
    fi
fi

# ============================================================
# Check remote
# ============================================================

if ! git remote get-url origin >/dev/null 2>&1; then
    echo "❌ Error: Git remote 'origin' was not found."
    exit 1
fi

REMOTE="$(git remote get-url origin)"

echo "🌐 Remote:"
echo "   ${REMOTE}"
echo

# ============================================================
# Git status
# ============================================================

echo "================================================"
echo " Git Status"
echo "================================================"
echo

git status --short

echo

# ============================================================
# Commit message
# ============================================================

read -r -p "📝 Commit message: " COMMIT_MESSAGE

if [[ -z "${COMMIT_MESSAGE}" ]]; then
    echo "❌ Commit message cannot be empty."
    exit 1
fi

# ============================================================
# Version
# ============================================================

echo
echo "================================================"
echo " Release Version"
echo "================================================"
echo
echo "Examples:"
echo "  1.0.0"
echo "  1.1.0"
echo "  2.0.0"
echo

read -r -p "🏷️ Version: " VERSION

# Remove v prefix if entered
VERSION="${VERSION#v}"

# ============================================================
# Validate version
# ============================================================

if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo
    echo "❌ Invalid version."
    echo "Expected format: 1.2.3"
    exit 1
fi

TAG="v${VERSION}"

echo
echo "📦 Release version: ${TAG}"

# ============================================================
# Check local tag
# ============================================================

if git rev-parse "${TAG}" >/dev/null 2>&1; then
    echo
    echo "❌ Tag ${TAG} already exists locally."
    echo
    echo "If you really want to recreate it:"
    echo "  git tag -d ${TAG}"
    echo "  git push origin --delete ${TAG}"
    exit 1
fi

# ============================================================
# Check remote tag
# ============================================================

if git ls-remote --exit-code --tags origin "refs/tags/${TAG}" >/dev/null 2>&1; then
    echo
    echo "❌ Tag ${TAG} already exists on GitHub."
    echo
    echo "To delete it:"
    echo "  git push origin --delete ${TAG}"
    exit 1
fi

# ============================================================
# Confirmation
# ============================================================

echo
echo "================================================"
echo " RELEASE SUMMARY"
echo "================================================"
echo
echo "Branch:  ${BRANCH}"
echo "Version: ${TAG}"
echo "Commit:  ${COMMIT_MESSAGE}"
echo "Remote:  ${REMOTE}"
echo

read -r -p "🚀 Create this release? [y/N]: " CONFIRM

if [[ ! "${CONFIRM}" =~ ^[Yy]$ ]]; then
    echo
    echo "Release cancelled."
    exit 0
fi

# ============================================================
# Git Add
# ============================================================

echo
echo "[1/6] 📁 Adding files..."

git add .

# ============================================================
# Git Commit
# ============================================================

echo
echo "[2/6] 📝 Creating commit..."

if git diff --cached --quiet; then
    echo "ℹ️ No changes to commit."
else
    git commit -m "${COMMIT_MESSAGE}"
fi

# ============================================================
# Push main
# ============================================================

echo
echo "[3/6] ⬆️ Pushing ${BRANCH}..."

git push origin "${BRANCH}"

# ============================================================
# Create Tag
# ============================================================

echo
echo "[4/6] 🏷️ Creating tag ${TAG}..."

git tag -a "${TAG}" -m "Release ${TAG}"

# ============================================================
# Push Tag
# ============================================================

echo
echo "[5/6] ⬆️ Pushing ${TAG} to GitHub..."

if ! git push origin "${TAG}"; then
    echo
    echo "❌ Failed to push tag."
    echo "Removing local tag..."
    git tag -d "${TAG}"
    exit 1
fi

# ============================================================
# Done
# ============================================================

echo
echo "[6/6] ✅ Release triggered successfully!"
echo

echo "================================================"
echo "           RELEASE CREATED"
echo "================================================"
echo
echo "🏷️ Version:"
echo "   ${TAG}"
echo
echo "GitHub Actions will now:"
echo
echo "   ✓ Run PHP syntax checks"
echo "   ✓ Run WordPress tests"
echo "   ✓ Install WooCommerce"
echo "   ✓ Activate the plugin"
echo "   ✓ Run runtime checks"
echo "   ✓ Build the ZIP"
echo "   ✓ Create GitHub Release"
echo "   ✓ Upload ZIP as Release Asset"
echo
echo "================================================"
echo

echo "🔗 GitHub Actions:"
echo "https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/actions"
echo

echo "🎉 Done!"

exit 0