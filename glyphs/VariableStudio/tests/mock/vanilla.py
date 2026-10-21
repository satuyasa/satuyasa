"""vanilla tiruan: cukup untuk menjalankan jendela tanpa macOS."""


class _W(object):
	def __init__(self, posSize=None, *args, **kw):
		self.posSize = posSize
		self.args = args
		self.kw = kw
		self.value = kw.get("value")
		self.items = list(args[0]) if args and isinstance(args[0], (list, tuple)) else []
		if args and isinstance(args[0], str):
			self.value = args[0]
		self.selection = []
		self.title = args[0] if args else None

	def get(self):
		if isinstance(self, (List,)):
			return self.items
		if isinstance(self, PopUpButton):
			return self.value or 0
		return self.value

	def set(self, v):
		if isinstance(self, List):
			self.items = list(v)
		else:
			self.value = v

	def setItems(self, items):
		self.items = list(items)
		if self.value is None or self.value >= len(self.items):
			self.value = 0

	def getItems(self):
		return self.items

	def getSelection(self):
		return self.selection

	def setSelection(self, s):
		self.selection = list(s)

	def setTitle(self, t):
		self.title = t

	def setMinValue(self, v):
		self.kw["minValue"] = v

	def setMaxValue(self, v):
		self.kw["maxValue"] = v

	def press(self):
		cb = self.kw.get("callback")
		if cb:
			cb(self)


class TextBox(_W): pass
class Button(_W): pass
class CheckBox(_W):
	def __init__(self, posSize, title, **kw):
		_W.__init__(self, posSize, title, **kw)
		self.value = kw.get("value", False)
class List(_W): pass
class PopUpButton(_W): pass
class EditText(_W): pass
class Slider(_W): pass
class TextEditor(_W): pass
class HorizontalLine(_W): pass


class _Group(object):
	pass


class Tabs(_W):
	def __init__(self, posSize, titles, **kw):
		_W.__init__(self, posSize, **kw)
		self.tabs = [_Group() for _ in titles]
		self.value = 0

	def __getitem__(self, i):
		return self.tabs[i]


class Window(object):
	def __init__(self, posSize, title, **kw):
		self.title = title
		self.binds = {}

	def bind(self, ev, cb):
		self.binds[ev] = cb

	def open(self):
		self.opened = True
