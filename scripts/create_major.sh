# This creates a new major version (e.g 1.0 > 2.0). Use for large changes

# Bump the version (this will update package.json)
npm version major

# Push the changes and tag
git push origin main
git push origin --tags
