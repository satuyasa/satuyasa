class GeneralPlugin(object):
	pass


class ReporterPlugin(object):
	def getScale(self):
		return 1.0

	texts = []

	def drawTextAtPoint(self, text, pos, fontSize=10.0, fontColor=None, align="bottomleft"):
		ReporterPlugin.texts.append((text, tuple(pos)))
