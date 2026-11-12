#!/bin/sh
# scripts/build_deploy_package.sh - Build icms_deploy.zip for web hosting (e.g. InfinityFree)
#
#   sh scripts/build_deploy_package.sh
#
# Packs the last commit (git archive): tests, the paper, backups, dumps and passwords are left out
# (see .gitattributes). deploy/local.php (database) and deploy/mail.local.php (Gmail) are added
# as config/local.php and config/mail.local.php when present, so the site works right after extracting.
set -e
cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
  echo "Note: you have uncommitted changes. Only the last commit is packaged - commit first to include them."
fi

rm -f icms_deploy.zip
git archive --format=zip -o icms_deploy.zip HEAD

# Host-only settings (not in Git): deploy/local.php -> config/local.php (database),
# deploy/mail.local.php -> config/mail.local.php (Gmail SMTP), deploy/ai.local.php -> config/ai.local.php (Gemini key)
for f in local.php mail.local.php ai.local.php; do
  if [ -f "deploy/$f" ]; then
    tmp=$(mktemp -d)
    mkdir -p "$tmp/config"
    cp "deploy/$f" "$tmp/config/$f"
    (cd "$tmp" && zip -q -u "$OLDPWD/icms_deploy.zip" "config/$f")
    rm -rf "$tmp"
    echo "Added deploy/$f as config/$f"
  else
    echo "No deploy/$f - create config/$f on the host yourself (copy config/${f%.php}.example.php)."
  fi
done

echo "Built icms_deploy.zip ($(du -h icms_deploy.zip | cut -f1)). Keep it private: it can contain the database and Gmail passwords."
