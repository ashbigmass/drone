var	Red = "#Bf616A",
	Blue = "#5B90Bf",
	Orange = "#Fdb45C",
	Yellow = "#Ebeb3B",
	Green = "#46Bfbd",
	Teal = "#96B5B4",
	PaleBlue = "#8Fa1B3",
	Purple = "#B45ECF",
	Brown = "#Ab7967";
	IndianRed = "#CD5C5C",
	LightCoral = "#F08080",
	Crimson = "#DC143C",
	Pink = "#FFC0CB",
	HotPink = "#FF69B4",
	DeepPink = "#FF1493",
	OrangeRed = "#FF4500",
	DarkOrange = "#FF8C00",
	Gold = "#FFD700",
	Plum = "#DDA0DD",
	Violet = "#EE82EE",
	Orchid = "#DA70D6",
	Lime = "#00FF00",
	LimeGreen = "#32CD32",
	PaleGreen = "#98FB98",
	AquaMarine = "#7FFFD4",
	Cyan = "#00FFFF",
	SkyBlue = "#87CEEB",
	RoyalBlue = "#4169E1",
	SteelBlue = "#4682B4",
	Navy = "#000080",
	Chocolate = "#D2691E",
	SaddleBrown = "#8B4513",
	Sienna = "#A0522D",
	GhostWhite = "#F8F8FF",
	WhiteSmoke = "#F5F5F5",
	Seashell = "#FFF5EE",
	Beige = "#F5F5DC",
	GoldenYellow = "#FFDF00",
	SlateGray = "#708090",
  	LightSlateGray  = "#778899";

var colors = [ Red, Orange, Yellow, Green, Blue, RoyalBlue, Purple, LightCoral, DarkOrange, Gold, Lime, SkyBlue, PaleBlue, Orchid, Crimson, OrangeRed, GoldenYellow, LimeGreen, SteelBlue, DeepPink, Navy, Plum, Cyan, Sienna, Teal, Pink, AquaMarine, SlateGray, WhiteSmoke, Brown, IndianRed, Seashell, SaddleBrown, HotPink, Beige, Violet, PaleGreen, Chocolate, LightSlateGray, GhostWhite ];

function Highlight(col, amt) {
	amt = amt || 30;
	var usePound = false;

	if (col[0] == "#") {
		col = col.slice(1);
		usePound = true;
	}

	var num = parseInt(col,16);

	var r = (num >> 16) + amt;

	if (r > 255) r = 255;
	else if  (r < 0) r = 0;

	var b = ((num >> 8) & 0x00FF) + amt;

	if (b > 255) b = 255;
	else if  (b < 0) b = 0;

	var g = (num & 0x0000FF) + amt;

	if (g > 255) g = 255;
	else if (g < 0) g = 0;

	return (usePound?"#":"") + (g | (b << 8) | (r << 16)).toString(16);
}

function showChart (myChart, id, data) {
	var canvas = document.getElementById(id);
	var ctx = canvas.getContext("2d");
	myChart = new Chart(ctx).Doughnut(data, {responsive : true});
	helpers = Chart.helpers;
	var legendHolder = document.createElement('div');
	legendHolder.innerHTML = myChart.generateLegend();
	// Include a html legend template after the module doughnut itself
	helpers.each(legendHolder.firstChild.childNodes, function(legendNode, index){
		helpers.addEvent(legendNode, 'mouseover', function(){
			var activeSegment = myChart.segments[index];
			activeSegment.save();
			activeSegment.fillColor = activeSegment.highlightColor;
			myChart.showTooltip([activeSegment]);
			activeSegment.restore();
		});
	});
	helpers.addEvent(legendHolder.firstChild, 'mouseout', function(){
		myChart.draw();
	});
	canvas.parentNode.appendChild(legendHolder.firstChild);
}
