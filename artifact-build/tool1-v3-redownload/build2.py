from pathlib import Path

_original_write_text = Path.write_text

def _safe_write_text(self, *args, **kwargs):
    self.parent.mkdir(parents=True, exist_ok=True)
    return _original_write_text(self, *args, **kwargs)

Path.write_text = _safe_write_text
code = Path('artifact-build/tool1-v3-redownload/build.py').read_text()
exec(compile(code, 'build.py', 'exec'), {'__name__': '__main__'})
