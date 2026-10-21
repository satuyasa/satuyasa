from Foundation import NSPoint


class _Any(object):
	"""Objek serba-bisa untuk menggambar: semua method diterima."""
	calls = []

	def __getattr__(self, name):
		def f(*a, **k):
			_Any.calls.append(name)
			return _Any()
		return f


class NSColor(object):
	def __getattr__(self, name):
		return _Any()
for _n in ("colorWithCalibratedRed_green_blue_alpha_", "colorWithCalibratedWhite_alpha_", "textColor"):
	setattr(NSColor, _n, staticmethod(lambda *a: _Any()))


class NSBezierPath(_Any):
	@staticmethod
	def bezierPath():
		return NSBezierPath()

	@staticmethod
	def bezierPathWithOvalInRect_(r):
		return NSBezierPath()


def NSRect(*a):
	return a


class NSMenuItem(object):
	@staticmethod
	def alloc():
		return NSMenuItem()

	def initWithTitle_action_keyEquivalent_(self, *a):
		self.args = a
		return self

	def setTarget_(self, t):
		self.target = t


class NSPasteboard(object):
	text = None

	@staticmethod
	def generalPasteboard():
		return NSPasteboard()

	def clearContents(self):
		pass

	def setString_forType_(self, s, t):
		NSPasteboard.text = s


class NSTimer(object):
	last = None

	@staticmethod
	def scheduledTimerWithTimeInterval_repeats_block_(i, r, block):
		t = NSTimer()
		t.block = block
		NSTimer.last = t
		return t

	def invalidate(self):
		self.block = None
