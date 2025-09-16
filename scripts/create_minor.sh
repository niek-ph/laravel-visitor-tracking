# This creates a new minor version (e.g 1.2 > 1.3). Use for minor changes

# Bump the version (this will update package.json)
npm version minor

# Push the changes and tag
git push origin main
git push origin --tags
