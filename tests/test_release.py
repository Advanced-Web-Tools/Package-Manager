"""Release checks need only Python's standard library and never contact GitHub."""

import importlib.util
import json
import os
import subprocess
import sys
from pathlib import Path
import tempfile
import unittest
from zipfile import ZipFile


ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("package_release", ROOT / "build/release.py")
release = importlib.util.module_from_spec(spec)
spec.loader.exec_module(release)


class ReleaseTests(unittest.TestCase):
    def test_first_release_is_always_one(self):
        for increment in ["major", "minor", "patch"]:
            self.assertEqual(release.next_version([], increment), "1.0.0")

    def test_semantic_increments_and_resets(self):
        tags = ["1.9.99", "v1.10.4", "1.2.8", "1.10.3"]
        for increment, expected in {"major": "2.0.0", "minor": "1.11.0", "patch": "1.10.5"}.items():
            self.assertEqual(release.next_version(tags, increment), expected)

    def test_unrelated_and_non_stable_tags_are_ignored(self):
        tags = ["latest", "nightly", "v9.0.0-beta", "2.0.0+build", "01.2.3"]
        self.assertEqual(release.next_version(tags, "patch"), "1.0.0")
        self.assertEqual(release.next_version(tags + ["0.9.0"], "minor"), "0.10.0")
        with self.assertRaises(ValueError):
            release.next_version([], "invalid")

    def test_real_package_has_flat_root_and_updated_manifest(self):
        original = (ROOT / "PackageManager/manifest.json").read_bytes()
        with tempfile.TemporaryDirectory() as temporary:
            name, archive = release.build_package(ROOT / "PackageManager", Path(temporary), "2.3.4")
            self.assertEqual(name, "PackageManager")
            self.assertEqual(archive.name, "PackageManager-2.3.4.zip")
            with ZipFile(archive) as bundle:
                names = bundle.namelist()
                self.assertIn("manifest.json", names)
                self.assertIn("main.php", names)
                self.assertIn("runtime/PackageManagerRouter.php", names)
                self.assertIn("views/assets/js/packages.js", names)
                self.assertFalse(any(name.startswith(("PackageManager/", "build/", "tests/", ".github/")) for name in names))
                self.assertEqual(json.loads(bundle.read("manifest.json"))["version"], "2.3.4")
                expected = {path.relative_to(ROOT / "PackageManager").as_posix() for path in (ROOT / "PackageManager").rglob("*") if path.is_file()}
                self.assertEqual(set(names), expected)
                self.assertIsNone(bundle.testzip())
        self.assertEqual((ROOT / "PackageManager/manifest.json").read_bytes(), original)

    def test_command_exports_workflow_outputs(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            tags = root / "tags.txt"
            tags.write_text("v1.2.9\n1.1.0\n")
            outputs = root / "github-output.txt"
            subprocess.run([
                sys.executable, "-B", str(ROOT / "build/release.py"),
                "--release-tags", str(tags), "--increment", "minor",
                "--package", str(ROOT / "PackageManager"), "--output", str(root / "assets"),
            ], check=True, capture_output=True, text=True, env={**os.environ, "GITHUB_OUTPUT": str(outputs)})
            values = dict(line.split("=", 1) for line in outputs.read_text().splitlines())
            self.assertEqual(values["version"], "1.3.0")
            self.assertEqual(values["tag"], "1.3.0")
            self.assertEqual(values["name"], "PackageManager")
            self.assertTrue(Path(values["archive"]).is_file())

    def test_rejects_nested_output(self):
        with self.assertRaises(ValueError):
            release.build_package(ROOT / "PackageManager", ROOT / "PackageManager/releases", "1.0.0")

    def test_rejects_symlinks(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            package = root / "package"
            package.mkdir()
            (package / "manifest.json").write_text('{"name":"PackageManager"}')
            (package / "main.php").write_text("<?php")
            (package / "linked.php").symlink_to(package / "main.php")
            with self.assertRaises(ValueError):
                release.build_package(package, root / "output", "1.0.0")


if __name__ == "__main__":
    unittest.main()
