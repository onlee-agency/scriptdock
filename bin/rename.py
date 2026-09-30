#!/usr/bin/env python3
"""
Rebrands the plugin in one go: display name, slug, text domain, PHP namespace,
function/option/meta/hook prefixes, constants, CSS classes, file names and,
optionally, the GitHub repository used for updates.

Usage (from the repository root):

    python3 bin/rename.py --name "Snippet Pilot" --slug snippet-pilot
    python3 bin/rename.py --name "Snippet Pilot" --slug snippet-pilot --repo acme/snippet-pilot
    python3 bin/rename.py ... --dry-run      # show what would change

What gets replaced (derived from the current name "ScriptDock" / "scriptdock"):

    ScriptDock          -> display name and PHP namespace (--name, --namespace)
    scriptdock          -> slug, text domain, folder, main file, CSS/JS names
    scriptdock_         -> PHP prefixes for options, meta keys, hooks, actions
    SCRIPTDOCK_         -> constants
    scriptdock://       -> stream wrapper protocol

Run it on a fresh copy and review the diff (git diff) before releasing.
Existing sites keep their data only if you do NOT rename after going live:
option names, meta keys and the post type change with the prefix.
"""

import argparse
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OLD_NAME = "ScriptDock"
OLD_SLUG = "scriptdock"

TEXT_EXTENSIONS = {".php", ".js", ".css", ".json", ".txt", ".md", ".html", ".yml", ".yaml", ".sh", ".py"}
SKIP_DIRS = {".git", "node_modules", "vendor", ".claude"}


def studly(name):
    """'Snippet Pilot' -> 'SnippetPilot' (valid PHP namespace)."""
    parts = re.split(r"[^A-Za-z0-9]+", name)
    result = "".join(p[:1].upper() + p[1:] for p in parts if p)
    if not result or not result[0].isalpha():
        sys.exit("The name must start with a letter.")
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--name", required=True, help='Display name, e.g. "Snippet Pilot"')
    parser.add_argument("--slug", required=True, help="Plugin slug / text domain, e.g. snippet-pilot")
    parser.add_argument("--prefix", help="PHP prefix (default: slug with dashes as underscores)")
    parser.add_argument("--namespace", help="PHP namespace (default: name without spaces)")
    parser.add_argument("--repo", help="GitHub owner/repo that publishes releases")
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    slug = args.slug.strip().lower()
    if not re.fullmatch(r"[a-z][a-z0-9-]{2,40}", slug):
        sys.exit("Slug must be 3-41 characters: lowercase letters, digits and dashes.")
    prefix = (args.prefix or slug.replace("-", "_")).lower()
    if not re.fullmatch(r"[a-z][a-z0-9_]{2,30}", prefix):
        sys.exit("Prefix must be 3-31 characters: lowercase letters, digits and underscores.")
    namespace = args.namespace or studly(args.name)
    const = prefix.upper()
    # WordPress limits post type names to 20 characters.
    post_type = prefix + "_snippet"
    if len(post_type) > 20:
        post_type = prefix.replace("_", "")[:12] + "_snippet"

    # JavaScript identifiers (window.scriptdock, scriptdockAdmin...) need camelCase: dashes are not allowed.
    camel = namespace[:1].lower() + namespace[1:]
    regex_replacements = [
        (re.compile(r"(?<=window\.)scriptdock\b"), camel),
        (re.compile(r"scriptdock(?=[A-Z])"), camel),
    ]

    # Order matters: most specific first.
    replacements = [
        ("scriptdock_snippet", post_type),
        ("scriptdock://", slug + "://"),
        ("SCRIPTDOCK_", const + "_"),
        ("scriptdock_", prefix + "_"),
        ("ScriptDock\\\\", namespace + "\\\\"),  # escaped namespaces in PHP strings
        ("ScriptDock\\", namespace + "\\"),
        ("namespace ScriptDock", "namespace " + namespace),
        ("@package ScriptDock", "@package " + namespace),
        ("scriptdock/snippet", slug + "/snippet"),  # block name
        ("scriptdock-", slug + "-"),  # CSS classes, handles, page slugs
        ("scriptdock", slug),  # text domain, slug, folder names
        ("ScriptDock", args.name),  # display name (last, after namespaces)
    ]

    changed = 0
    for base, dirs, files in os.walk(ROOT):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for filename in files:
            path = os.path.join(base, filename)
            if os.path.splitext(filename)[1] not in TEXT_EXTENSIONS or path == os.path.abspath(__file__):
                continue
            with open(path, encoding="utf-8") as handle:
                text = handle.read()
            new = text
            for pattern, value in regex_replacements:
                new = pattern.sub(value, new)
            for old, value in replacements:
                new = new.replace(old, value)
            if args.repo and filename == OLD_SLUG + ".php":
                new = re.sub(r"(define\( '" + const + r"_UPDATE_REPO', )'[^']*'", r"\g<1>'" + args.repo + "'", new)
                new = re.sub(r"((?:Update|Plugin) URI:\s+)https://github\.com/\S*", r"\g<1>https://github.com/" + args.repo, new)
            if new != text:
                changed += 1
                print(("would update " if args.dry_run else "updated ") + os.path.relpath(path, ROOT))
                if not args.dry_run:
                    with open(path, "w", encoding="utf-8") as handle:
                        handle.write(new)

    # Rename files and folders whose names contain the old slug (deepest first).
    renames = []
    for base, dirs, files in os.walk(ROOT, topdown=False):
        if any(part in SKIP_DIRS for part in base.split(os.sep)):
            continue
        for name in files + dirs:
            if OLD_SLUG in name:
                renames.append((os.path.join(base, name), os.path.join(base, name.replace(OLD_SLUG, slug))))
    for old, new in renames:
        print(("would rename " if args.dry_run else "renamed ") + os.path.relpath(old, ROOT) + " -> " + os.path.relpath(new, ROOT))
        if not args.dry_run:
            os.rename(old, new)

    print(f"\n{changed} files updated, {len(renames)} paths renamed. Namespace {namespace}, prefix {prefix}_, constants {const}_*.")
    if not args.repo:
        print("Tip: pass --repo owner/repo to enable updates from GitHub releases.")


if __name__ == "__main__":
    main()
