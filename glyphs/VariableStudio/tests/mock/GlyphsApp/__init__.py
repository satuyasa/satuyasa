"""Glyphs 3 tiruan untuk tes (hanya API yang dipakai Variable Studio)."""
import copy as _copy
import uuid as _uuid

from Foundation import NSPoint

UPDATEINTERFACE = "GSUpdateInterface"
WINDOW_MENU = "WINDOW_MENU"
INSTANCETYPESINGLE = 0
INSTANCETYPEVARIABLE = 1
MAKE_FIRST_MODE = ["last"]  # "last": node jadi indeks terakhir (titik awal Glyphs); "first": indeks 0
MESSAGES = []


def Message(title="", message="", OKButton=None):
	MESSAGES.append((title, message))


class _Glyphs(object):
	def __init__(self):
		self.font = None
		self.defaults = {}
		self.menu = {WINDOW_MENU: []}
		self.callbacks = []
		self.redraws = 0

	def redraw(self):
		self.redraws += 1

	def addCallback(self, fn, ev):
		self.callbacks.append((fn, ev))

	def removeCallback(self, fn, ev):
		self.callbacks = [c for c in self.callbacks if c[0] != fn]


Glyphs = _Glyphs()


class GSCustomParameter(object):
	def __init__(self, name, value):
		self.name = name
		self.value = value


class _CP(object):
	def __init__(self):
		self.items = []

	def __getitem__(self, key):
		for p in self.items:
			if p.name == key:
				return p.value
		return None

	def __setitem__(self, key, value):
		for p in self.items:
			if p.name == key:
				p.value = value
				return
		self.items.append(GSCustomParameter(key, value))

	def __delitem__(self, key):
		for p in self.items:
			if p.name == key:
				self.items.remove(p)
				return

	def append(self, p):
		self.items.append(p)

	def all(self, key):
		return [p.value for p in self.items if p.name == key]


class GSAxis(object):
	def __init__(self):
		self.name = ""
		self.axisTag = ""
		self.axisId = str(_uuid.uuid4())
		self.hidden = False


class _AxesProxy(object):
	def __init__(self, owner):
		self.o = owner

	def _ids(self):
		f = self.o.font
		return [a.axisId for a in f.axes] if f is not None else []

	def __iter__(self):
		return iter([self.o._vals.get(i, 0.0) for i in self._ids()])

	def __len__(self):
		return len(self._ids())

	def __getitem__(self, i):
		return list(self)[i]

	def __setitem__(self, i, v):
		self.o._vals[self._ids()[i]] = float(v)


class _HasAxes(object):
	def _get_axes(self):
		return _AxesProxy(self)

	def _set_axes(self, values):
		if self.font is None:
			return
		for a, v in zip(self.font.axes, values):
			self._vals[a.axisId] = float(v)
	axes = property(_get_axes, _set_axes)


class GSFontMaster(_HasAxes):
	def __init__(self, name="Regular"):
		self.name = name
		self.id = str(_uuid.uuid4()).upper()
		self.font = None
		self._vals = {}
		self.ascender = 800
		self.capHeight = 700
		self.xHeight = 500
		self.descender = -200
		self.italicAngle = 0.0
		self.stems = []
		self.customParameters = _CP()

	def copy(self):
		m = GSFontMaster(self.name)
		m.id = self.id
		m._vals = dict(self._vals)
		for k in ("ascender", "capHeight", "xHeight", "descender", "italicAngle"):
			setattr(m, k, getattr(self, k))
		m.stems = list(self.stems)
		m.customParameters = _copy.deepcopy(self.customParameters)
		return m

	def __copy__(self):
		return self.copy()


class GSInstance(_HasAxes):
	def __init__(self, type=0):
		self.type = type
		self.name = "Regular"
		self.font = None
		self._vals = {}
		self.weightClass = 400
		self.widthClass = 5
		self.isItalic = False
		self.isBold = False
		self.linkStyle = ""
		self.customParameters = _CP()

	@property
	def interpolatedFont(self):
		"""Interpolasi sederhana untuk tes: salin master terdekat."""
		f = self.font
		vals = list(self.axes)
		best = min(f.masters, key=lambda m: sum((a - b) ** 2 for a, b in zip(list(m.axes), vals)))
		nf = GSFont()
		nm = GSFontMaster(self.name)
		nf.masters.append(nm)
		for g in f.glyphs:
			ng = GSGlyph(g.name)
			nf.glyphs.append(ng)
			ng.layers[nm.id] = g.layers[best.id].copy()
		return nf


class GSNode(object):
	def __init__(self, pos=(0, 0), type="line", smooth=False):
		self.position = NSPoint(*pos)
		self.type = type
		self.smooth = smooth
		self.parent = None
		self.selected = False

	def makeNodeFirst(self):
		p = self.parent
		i = p.nodes.index(self)
		if MAKE_FIRST_MODE[0] == "first":
			p.nodes[:] = p.nodes[i:] + p.nodes[:i]
		else:
			p.nodes[:] = p.nodes[i + 1:] + p.nodes[:i + 1]


class GSPath(object):
	def __init__(self, nodes=(), closed=True):
		self.nodes = []
		self.closed = closed
		for n in nodes:
			self.addNode(n)

	def addNode(self, n):
		n.parent = self
		self.nodes.append(n)

	def applyTransform(self, m):
		for n in self.nodes:
			x, y = n.position
			n.position = NSPoint(m[0] * x + m[2] * y + m[4], m[1] * x + m[3] * y + m[5])

	def reverse(self):
		types = [n.type for n in self.nodes]
		k = len(types)
		on = [i for i, t in enumerate(types) if t != "offcurve"]
		new = list(types)
		for idx, i in enumerate(on):
			j = on[(idx + 1) % len(on)]
			new[i] = types[j]
		for n, t in zip(self.nodes, new):
			n.type = t
		self.nodes.reverse()

	def copy(self):
		p = GSPath(closed=self.closed)
		for n in self.nodes:
			p.addNode(GSNode(tuple(n.position), n.type, n.smooth))
		return p


class GSAnchor(object):
	def __init__(self, name, pos):
		self.name = name
		self.position = NSPoint(*pos)


class GSComponent(object):
	def __init__(self, name, pos=(0, 0)):
		self.componentName = name
		self.position = NSPoint(*pos)
		self.scale = (1.0, 1.0)
		self.rotation = 0.0


class GSLayer(object):
	def __init__(self):
		self.paths = []
		self.anchors = []
		self.components = []
		self.width = 600.0
		self.parent = None
		self.layerId = None
		self.associatedMasterId = None
		self.isSpecialLayer = False
		self.attributes = {}
		self.selection = []

	def copy(self):
		l = GSLayer()
		l.paths = [p.copy() for p in self.paths]
		l.anchors = [GSAnchor(a.name, tuple(a.position)) for a in self.anchors]
		l.components = [GSComponent(c.componentName, tuple(c.position)) for c in self.components]
		l.width = self.width
		l.isSpecialLayer = self.isSpecialLayer
		l.attributes = dict(self.attributes)
		return l

	def copyDecomposedLayer(self):
		l = self.copy()
		font = self.parent.parent if self.parent else None
		for c in self.components:
			base = font.glyphs[c.componentName].layers[self.associatedMasterId]
			for p in base.copyDecomposedLayer().paths:
				q = p.copy()
				q.applyTransform((1, 0, 0, 1, c.position.x, c.position.y))
				l.paths.append(q)
		l.components = []
		return l


class _Layers(object):
	def __init__(self, glyph):
		self.g = glyph
		self.d = {}

	def __getitem__(self, key):
		if isinstance(key, int):
			key = self.g.parent.masters[key].id
		return self.d.get(key)

	def __setitem__(self, key, layer):
		layer.layerId = key
		if layer.associatedMasterId is None:
			layer.associatedMasterId = key
		layer.parent = self.g
		self.d[key] = layer

	def __iter__(self):
		return iter(list(self.d.values()))

	def __len__(self):
		return len(self.d)


class GSGlyph(object):
	def __init__(self, name):
		self.name = name
		self.parent = None
		self.layers = _Layers(self)
		self.color = None
		self.undo = 0

	def beginUndo(self):
		self.undo += 1

	def endUndo(self):
		self.undo -= 1


class _Glyphs_List(list):
	def __init__(self, font):
		list.__init__(self)
		self.font = font

	def __getitem__(self, k):
		if isinstance(k, str):
			for g in self:
				if g.name == k:
					return g
			return None
		return list.__getitem__(self, k)

	def append(self, g):
		g.parent = self.font
		list.append(self, g)


class _Masters(list):
	def __init__(self, font):
		list.__init__(self)
		self.font = font

	def append(self, m):
		m.font = self.font
		list.append(self, m)
		for g in self.font.glyphs:
			if g.layers[m.id] is None:
				g.layers[m.id] = GSLayer()


class _Owned(list):
	def __init__(self, font):
		list.__init__(self)
		self.font = font

	def append(self, x):
		x.font = self.font
		list.append(self, x)


class GSFont(object):
	def __init__(self):
		self.familyName = "Tes"
		self.axes = _Owned(self)
		self.glyphs = _Glyphs_List(self)
		self.masters = _Masters(self)
		self.instances = _Owned(self)
		self.customParameters = _CP()
		self.kerning = {}
		self.selectedLayers = []
		self.tabs = []

	def setKerningForPair(self, mid, l, r, v):
		self.kerning.setdefault(mid, {}).setdefault(l, {})[r] = v

	def glyphForId_(self, k):
		return None

	def newTab(self, text):
		self.tabs.append(text)

	def disableUpdateInterface(self):
		pass

	def enableUpdateInterface(self):
		pass
