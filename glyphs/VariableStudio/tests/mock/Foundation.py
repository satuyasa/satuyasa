class NSPoint(tuple):
	def __new__(cls, x=0, y=0):
		if isinstance(x, (tuple, list)):
			x, y = x
		return tuple.__new__(cls, (float(x), float(y)))
	x = property(lambda s: s[0])
	y = property(lambda s: s[1])
