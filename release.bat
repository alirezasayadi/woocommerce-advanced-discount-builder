@echo off
setlocal EnableDelayedExpansion

title WooCommerce Discount Builder - Release

echo.
echo ================================================
echo   Advanced WooCommerce Discount Code Generator
echo   Release Tool
echo ================================================
echo.

REM ================================================
REM Check Git
REM ================================================

where git >nul 2>&1

if errorlevel 1 (
    echo [ERROR] Git is not installed or not in PATH.
    pause
    exit /b 1
)

REM ================================================
REM Check repository
REM ================================================

git rev-parse --is-inside-work-tree >nul 2>&1

if errorlevel 1 (
    echo [ERROR] This folder is not a Git repository.
    echo.
    echo Run this BAT file inside the project folder.
    pause
    exit /b 1
)

REM ================================================
REM Check branch
REM ================================================

for /f "delims=" %%B in ('git branch --show-current') do set BRANCH=%%B

echo Current branch: !BRANCH!
echo.

if /I not "!BRANCH!"=="main" (
    echo [WARNING] You are not on the main branch.
    echo.
    choice /C YN /M "Continue anyway"

    if errorlevel 2 (
        echo Cancelled.
        pause
        exit /b 0
    )
)

REM ================================================
REM Check remote
REM ================================================

git remote get-url origin >nul 2>&1

if errorlevel 1 (
    echo [ERROR] Git remote 'origin' was not found.
    pause
    exit /b 1
)

for /f "delims=" %%R in ('git remote get-url origin') do set REMOTE=%%R

echo Remote:
echo !REMOTE!
echo.

REM ================================================
REM Show status
REM ================================================

echo ================================================
echo Git Status
echo ================================================
echo.

git status --short

echo.

REM ================================================
REM Ask commit message
REM ================================================

set "COMMIT_MESSAGE="

set /p "COMMIT_MESSAGE=Commit message: "

if "!COMMIT_MESSAGE!"=="" (
    echo [ERROR] Commit message cannot be empty.
    pause
    exit /b 1
)

REM ================================================
REM Ask version
REM ================================================

echo.
echo ================================================
echo Release Version
echo ================================================
echo.

echo Example:
echo   1.0.0
echo   1.1.0
echo   2.0.0
echo.

set "VERSION="

set /p "VERSION=Version: "

if "!VERSION!"=="" (
    echo [ERROR] Version cannot be empty.
    pause
    exit /b 1
)

REM Remove v prefix if user entered it

if /I "!VERSION:~0,1!"=="v" (
    set "VERSION=!VERSION:~1!"
)

REM ================================================
REM Validate version
REM ================================================

echo.

for /f "tokens=1,2,3,4 delims=." %%a in ("!VERSION!") do (
    set "V1=%%a"
    set "V2=%%b"
    set "V3=%%c"
    set "V4=%%d"
)

if "!V1!"=="" goto invalid_version
if "!V2!"=="" goto invalid_version
if "!V3!"=="" goto invalid_version
if not "!V4!"=="" goto invalid_version

for /f "delims=0123456789" %%A in ("!V1!!V2!!V3!") do (
    goto invalid_version
)

echo Version: v!VERSION!
echo.

goto version_valid

:invalid_version

echo [ERROR] Invalid version format.
echo Expected format: 1.2.3
pause
exit /b 1

:version_valid

REM ================================================
REM Check if tag already exists
REM ================================================

git rev-parse "v!VERSION!" >nul 2>&1

if not errorlevel 1 (
    echo [ERROR] Tag v!VERSION! already exists.
    echo.
    echo If you deleted the tag locally but it still exists remotely,
    echo you may need to delete the remote tag first.
    echo.
    echo Command:
    echo git push origin --delete v!VERSION!
    echo.
    pause
    exit /b 1
)

git ls-remote --tags origin "refs/tags/v!VERSION!" | findstr /C:"refs/tags/v!VERSION!" >nul

if not errorlevel 1 (
    echo [ERROR] Tag v!VERSION! already exists on GitHub.
    pause
    exit /b 1
)

REM ================================================
REM Confirmation
REM ================================================

echo.
echo ================================================
echo RELEASE SUMMARY
echo ================================================
echo.
echo Branch:   !BRANCH!
echo Version:  v!VERSION!
echo Commit:   !COMMIT_MESSAGE!
echo Remote:   !REMOTE!
echo.
echo ================================================
echo.

choice /C YN /M "Create this release"

if errorlevel 2 (
    echo.
    echo Release cancelled.
    pause
    exit /b 0
)

REM ================================================
REM Git Add
REM ================================================

echo.
echo [1/6] Adding files...

git add .

if errorlevel 1 (
    echo [ERROR] git add failed.
    pause
    exit /b 1
)

REM ================================================
REM Git Commit
REM ================================================

echo.
echo [2/6] Creating commit...

git diff --cached --quiet

if errorlevel 1 (
    git commit -m "!COMMIT_MESSAGE!"

    if errorlevel 1 (
        echo [ERROR] Commit failed.
        pause
        exit /b 1
    )
) else (
    echo No new changes to commit.
)

REM ================================================
REM Push main
REM ================================================

echo.
echo [3/6] Pushing main branch...

git push origin main

if errorlevel 1 (
    echo [ERROR] Push failed.
    pause
    exit /b 1
)

REM ================================================
REM Create tag
REM ================================================

echo.
echo [4/6] Creating tag v!VERSION!...

git tag -a "v!VERSION!" -m "Release v!VERSION!"

if errorlevel 1 (
    echo [ERROR] Failed to create tag.
    pause
    exit /b 1
)

REM ================================================
REM Push tag
REM ================================================

echo.
echo [5/6] Pushing tag to GitHub...

git push origin "v!VERSION!"

if errorlevel 1 (
    echo [ERROR] Failed to push tag.
    echo.
    echo Removing local tag...
    git tag -d "v!VERSION!"
    pause
    exit /b 1
)

REM ================================================
REM Done
REM ================================================

echo.
echo [6/6] Release triggered successfully!
echo.

echo ================================================
echo              RELEASE CREATED
echo ================================================
echo.
echo Version:
echo   v!VERSION!
echo.
echo GitHub Actions should now:
echo   1. Run PHP syntax tests
echo   2. Install WordPress
echo   3. Install WooCommerce
echo   4. Test plugin activation
echo   5. Run runtime checks
echo   6. Build the plugin ZIP
echo   7. Create GitHub Release
echo   8. Upload ZIP as Release Asset
echo.
echo ================================================
echo.

echo GitHub Actions:
echo https://github.com/alirezasayadi/woocommerce-advanced-discount-builder/actions
echo.

pause
exit /b 0