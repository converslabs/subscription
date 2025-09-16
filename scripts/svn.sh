#!/bin/bash

SVN_URL="https://plugins.svn.wordpress.org/subscription/"
SVN_DIR="svn"
RELEASE_DIR="./releases/subscription"

# Check if svn folder exists
if [ ! -d "$SVN_DIR" ]; then
    echo "SVN folder not found. Cloning from $SVN_URL..."
    svn checkout "$SVN_URL" "$SVN_DIR"
else
    echo "SVN folder exists."
fi

cd "$SVN_DIR"

# Check if release directory exists
if [ ! -d "$RELEASE_DIR" ]; then
    echo "Release directory not found at $RELEASE_DIR"
    echo "Please ensure the release folder exists with all plugin files"
    exit 1
fi


echo "What type of change do you want to push? (assets/tag)"
read -r CHANGE_TYPE

if [ "$CHANGE_TYPE" = "assets" ]; then
    echo "Pushing assets (readme.txt and .wordpress-org) to SVN..."
    cp ../readme.txt trunk/readme.txt
    if [ -d "../.wordpress-org" ]; then
        cp -r ../.wordpress-org/* assets/
    fi
    svn add --force assets/* trunk/readme.txt
    svn commit -m "Update assets and readme.txt"
    exit 0
fi

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
        if [ -e "$RELEASE_DIR/$ITEM" ]; then
            cp -r "$RELEASE_DIR/$ITEM" trunk/
            cp -r "$RELEASE_DIR/$ITEM" "$TAG_DIR/"
        fi
    done

    svn add --force trunk/* "$TAG_DIR"/*
    svn commit -m "Release version $VERSION"
    exit 0
fi

echo "Unknown change type. Exiting."
exit 1 