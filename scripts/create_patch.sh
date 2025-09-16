# This creates a new patch (e.g 1.1.1 > 1.1.2). Use for small changes like README updates

# Bump the version (this will update package.json)
npm version patch

# Push the changes and tag
git push origin main
git push origin --tags
