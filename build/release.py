"""Build the installable package ZIP from published release versions."""

import argparse
import json
import os
from pathlib import Path
import re
import shutil
import tempfile
from zipfile import ZIP_DEFLATED, ZipFile


VERSION_TAG = re.compile(r"v?(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)")


def next_version(tags: list[str], increment: str) -> str:
    if increment not in {"major", "minor", "patch"}:
        raise ValueError("Choose major, minor or patch.")
    versions = []
    for tag in tags:
        match = VERSION_TAG.fullmatch(tag.strip())
        if match:
            versions.append(tuple(map(int, match.groups())))
    if not versions:
        return "1.0.0"
    major, minor, patch = max(versions)
    if increment == "major":
        return f"{major + 1}.0.0"
    if increment == "minor":
        return f"{major}.{minor + 1}.0"
    return f"{major}.{minor}.{patch + 1}"


def build_package(package: Path, output: Path, version: str) -> tuple[str, Path]:
    if not VERSION_TAG.fullmatch(version) or version.startswith("v"):
        raise ValueError("The manifest version must use major.minor.patch.")
    package = package.resolve()
    for required in ("manifest.json", "main.php"):
        if not (package / required).is_file():
            raise ValueError(f"Package is missing {required}.")
    for path in package.rglob("*"):
        if path.is_symlink():
            raise ValueError("Package archives cannot include symbolic links.")
    manifest = json.loads((package / "manifest.json").read_text(encoding="utf-8"))
    name = manifest["name"]
    if not isinstance(name, str) or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_-]*", name):
        raise ValueError("The package name must be safe for a release filename.")
    output = output.resolve()
    if output == package or package in output.parents:
        raise ValueError("Build output must be outside the package directory.")
    output.mkdir(parents=True, exist_ok=True)
    archive = output / f"{name}-{version}.zip"

    # Update a staging copy before archiving; the checked-out source stays untouched.
    with tempfile.TemporaryDirectory(prefix="awt-release-") as temporary:
        staging = Path(temporary) / "package"
        shutil.copytree(package, staging)
        manifest["version"] = version
        (staging / "manifest.json").write_text(
            json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8"
        )
        with ZipFile(archive, "w", ZIP_DEFLATED) as bundle:
            for path in sorted(staging.rglob("*")):
                if path.is_file():
                    bundle.write(path, path.relative_to(staging).as_posix())

    with ZipFile(archive) as bundle:
        if "manifest.json" not in bundle.namelist() or "main.php" not in bundle.namelist():
            raise ValueError("The archive must contain package code directly at its root.")
        if json.loads(bundle.read("manifest.json"))["version"] != version:
            raise ValueError("The archive manifest version does not match its release.")
    return name, archive


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--release-tags", type=Path, required=True)
    parser.add_argument("--increment", choices=["major", "minor", "patch"], required=True)
    parser.add_argument("--package", type=Path, default=Path("PackageManager"))
    parser.add_argument("--output", type=Path, default=Path("releases"))
    args = parser.parse_args()
    tags = args.release_tags.read_text(encoding="utf-8").splitlines()
    version = next_version(tags, args.increment)
    name, archive = build_package(args.package, args.output, version)
    if os.environ.get("GITHUB_OUTPUT"):
        with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as outputs:
            for key, value in {"name": name, "version": version, "tag": version, "archive": archive}.items():
                outputs.write(f"{key}={value}\n")
    print(f"Built {archive.name} for release {version}")


if __name__ == "__main__":
    main()
