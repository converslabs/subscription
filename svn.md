## WordPress.org SVN Structure

```
svn/
├── trunk/          # Development version (latest code)
├── tags/           # Release versions
│   ├── 1.3.0/
│   ├── 1.3.1/
│   ├── 1.4.0/
│   └── 1.5.0/
└── assets/         # Plugin assets (banner, icon, screenshots)
```

## The Process

1. **Development**: You work in `trunk/` - this is where you add, modify, and update files
2. **Release**: When you're ready to release a new version, you create a tag by copying files from `trunk/` to `tags/version/`
3. **WordPress.org**: WordPress.org serves the plugin from the `tags/` directory, not `trunk/`

## How Tag Creation Works

Looking at your `svn.sh` script, here's what happens when you create a tag:

```bash
# From your svn.sh script
if [ "$CHANGE_TYPE" = "tag" ]; then
    echo "Enter the new version number (e.g., 1.2.3):"
    read -r VERSION
    TAG_DIR="tags/$VERSION"

    # Remove old tag if exists
    if [ -d "$TAG_DIR" ]; then
        svn rm "$TAG_DIR" --force
    fi
    mkdir -p "$TAG_DIR"
    svn add "$TAG_DIR"

    # List of files/folders to copy
    FILES=(assets build includes templates vendor composer.json index.php readme.txt subscription.php)
    for ITEM in "${FILES[@]}"; do
        if [ -e "../$ITEM" ]; then
            cp -r ../$ITEM trunk/
            cp -r ../$ITEM "$TAG_DIR/"
        fi
    done

    svn add --force trunk/* "$TAG_DIR"/*
    svn commit -m "Release version $VERSION"
fi
```

## The Steps:

1. **Copy to trunk**: Files from your plugin directory are copied to `trunk/`
2. **Copy to tag**: The same files are copied to `tags/1.5.0/` (or whatever version)
3. **Commit**: Both trunk and tag are committed to SVN

## Why This Structure?

- **trunk/**: Always contains the latest development version
- **tags/**: Contains stable, released versions that users can download
- **assets/**: Contains plugin metadata (banner, icon, screenshots)

## Important codes example

```bash
# 1. Remove all files from trunk forcefully
svn remove trunk/* --force

# 2. Commit the removal to clear SVN tracking
svn commit -m "Remove all files from trunk for clean restart"

# 3. Copy fresh files from release directory to trunk
cp -r ../release/subscription/* trunk/

# 4. Add all new files to SVN
svn add trunk/* --force

# 5. Commit the fresh files
svn commit -m "Add fresh plugin files to trunk"
```
