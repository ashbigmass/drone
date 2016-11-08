	var cl=0, clearth=0, ldir=true, rdir=true, tdir=false, atcdir=false, earthdir=false;
	var map;
	var toggle_actc;
	var toggle_liveactc;
	var i, init;
	var marker, cmarker, markerlabel1, markerlabel2, apmarker, apcmarker, pathmarker, liveatcmarker, fixlabelmarker, fixlabelmarker2;
	var markers = [], cmarkers = [], cmarkercont= [], markerlabels1 = [], markerlabels2 = [], apmarkers = [], apcmarkers = [], pathmarkers = [], circles = [], liveatcmarkers = []; 
	var fixlabelmarkers = [], fixlabelmarkers2 = [];
	var markersopen,  cloudLayer, weatherLayer, ctaLayer, ctaLayer2, ctaLayer3;
	var wpLayer = [null, null, null];
	var prevcenter, prevzoomlevel;
	var footline = [], line = [], tailline = [];
	var members = [], liveatcicao = [];
	var viewmeter, panto = '';
	var apcitymap = {};
	var dn = null;
	var dispcircle = true;
	var markeriw;
	var svg_oldhead = 0;
	var svg_oldelevation = 0.0001;
	var svg_oldspd = 0;
	var svg_oldalt = 0;
	var svg_oldpitch = 0;
	var hudcolor = '#414141';
	var huddisp = false, rosedisp=false;
		
	// Cretes the map
	function initialize() {
		map = new google.maps.Map(document.getElementById('map'),
			{zoom: 10,
			center: new google.maps.LatLng(37.55, 126.88),
			mapTypeControl: true,
			mapTypeControlOptions: {
			 	style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
			},
			mapTypeId: google.maps.MapTypeId.ROADMAP,
			elementType: "geometry",
			stylers: [     { visibility: 'off' }   ]
		});

		google.maps.event.addListenerOnce(map, 'idle', function(){
		});
		init = 0;
		markersopen = 0;

		ctaLayer = new google.maps.KmlLayer({
			preserveViewport: true,
    			url: 'http://220.230.100.125/~ash/xplane/country.kmz'
  		});
  		ctaLayer.setMap(null);

		google.maps.event.addListener(ctaLayer, 'zoom_changed', function() {
	  		ctaLayer.setMap(map);
		});

		ctaLayer1 = new google.maps.KmlLayer({
    			url: 'http://www.nhc.noaa.gov/gis/forecast/archive/latest_wsp34knt120hr_tenthDeg.kmz'
  		});
  		ctaLayer1.setMap(null);

		ctaLayer2 = new google.maps.KmlLayer({
    			url: 'http://www.nhc.noaa.gov/gis/kml/nhc.kmz'
  		});
  		ctaLayer2.setMap(null);

		ctaLayer3 = new google.maps.KmlLayer({
			preserveViewport: true,
    			url: 'http://220.230.100.125/~ash/xplane/RK_Prohibited.kmz'
  		});
  		ctaLayer3.setMap(map);

		weatherLayer = new google.maps.weather.WeatherLayer({
			temperatureUnits: google.maps.weather.TemperatureUnit.CELSIUS,
			windSpeedUnits: google.maps.weather.WindSpeedUnit.KILOMETERS_PER_HOUR
		});
		weatherLayer.setMap(null);

		cloudLayer = new google.maps.weather.CloudLayer();
		cloudLayer.setMap(map);

	}	

	function generateAirport(APlocations) {
		function addAPMarker(i, name, rmk, x, y, ic, viewmeter) {
			apmarker = new google.maps.Marker({
				map: map,
				position: new google.maps.LatLng(x, y),
				icon: ic,
			       clickable : true,
			       title: name
			});

			google.maps.event.addListener(apmarker, 'click', function () {
				var iw = new google.maps.InfoWindow();
				iw.setOptions({
					content: "<br>"+rmk,
					maxWidth: 400
				});
				iw.open(map, this);
				map.setCenter(this.getPosition());
				prevzoomlevel = map.getZoom();
				google.maps.event.addListener(iw,'closeclick',function() {
					iw.close();
				});
			});
			apmarkers.push(apmarker);
		}	
		
		function addAPCircleMarker(i, name, rmk, x, y, ic, viewmeter) {
			apcmarker = new google.maps.Marker({
				map: map,
				position: new google.maps.LatLng(x, y),
				icon: ic,
			    clickable : true,
			    title: name
			});
	
			
			var smiter = (((x) * 1000) / 60) * 2;
			smiter = 3300;
					
			// Add the circle 
			var apcmarkerOptions = {
      					strokeColor: '#F79F50',
	      				strokeOpacity: 0.9,
      					strokeWeight: 1,
      					fillColor: '#FF0000',
		      			fillOpacity: 0.1,
      					map: map,
      					center: new google.maps.LatLng(x, y),
						clickable : true,
						infoWindowIndex: i,
	     					radius: smiter
			};
			apcmarker = new google.maps.Circle(apcmarkerOptions);
					
			apcmarkers.push(apcmarker);		
		}

		for (i = 0; i < APlocations.length; i++) {
//			addAPMarker(i, APlocations[i][0], APlocations[i][1], APlocations[i][2], APlocations[i][3], APlocations[i][4]);
			addAPCircleMarker(i, APlocations[i][0], APlocations[i][1], APlocations[i][2], APlocations[i][3], APlocations[i][4]);
 			addAPMarker(i, APlocations[i][0], APlocations[i][1], APlocations[i][2], APlocations[i][3], APlocations[i][4]);

		}
		toggle_actc = 1;
	}

	function gotoMembers(x,y) {
		map.setZoom(11);
		map.setCenter(new google.maps.LatLng(x, y));
	}

	function clearMembers() {
		//$("#viewmembers").empty();
		var div_cont = document.getElementById('viewmembers');
         	div_cont.innerHTML = '<table><tr><td> </td></tr></table>';
	}

	function generateTails(locations) {
		var tailCoordinates = [];

		for (i = 0; i < tailline.length; i++) {
			tailline[i].setMap(null); //or line[i].setVisible(false);
		}
		var orgname = locations[0][0];
		var j = 0;
		for (var i = 0; i < locations.length; i++) {
			if (i == locations.length-1) orgname = '';
			if (locations[i][0] == orgname) {
				tailCoordinates[j] = new google.maps.LatLng(locations[i][1], locations[i][2]);
				j = j + 1;
			} else {
				// tail path
				var tailPath = new google.maps.Polyline({
					path: tailCoordinates,
					strokeColor: "#EE6554",
					strokeOpacity: 0.8,
					strokeWeight: 2,
					zindex: 10
				});
				tailPath.setMap(map);
				tailline.push(tailPath);
				tailCoordinates = [];
				j = 0;
				orgname = locations[i][0];
			}
		}
	}

	function generateMembers(contm) {
		var div_cont = document.getElementById('viewmembers');
         	div_cont.innerHTML = '<table>'+contm.join('')+'</table>';
	}

	function commaNum(num) {  
		var len, point, str;  
  
		num = num + "";  
		point = num.length % 3  
		len = num.length;  
  
		str = num.substring(0, point);  
		while (point < len) {  
            	if (str != "") str += ",";  
            	str += num.substring(point, point + 3);  
            	point += 3;  
        	}  
        	return str;  
	}

	function sleep(milliseconds) {
		var start = new Date().getTime();
		for (var i = 0; i < 1e7; i++) {
			if ((new Date().getTime() - start) > milliseconds){
			break;
			}
  		}
	}
    
	function pantof(name) {
		if (panto == name) {
			panto = '';
			var div_cont = document.getElementById('panto-cont');
			div_cont.innerHTML = '<div width=330 style=\'border-style:none; overflow-x:hidden; overflow-y:hidden\'><font color=white><center><br><br><br>'+
						'<font style=\'font-family: Tahoma; font-size: 14px;\'>Panto Mode Close'+
						'</div>';
			sleep(800);
	  		$(".slidepanto").stop().animate({left: '-1000px'}, 400);
  			if (huddisp == true) {
		  		$(".slidehud").stop().animate({left: '-1000px'}, 400);
				$("div#hud-cont").css({"left":"-1000px"});
  			}	
  			if (rosedisp == true) {
		  		$(".sliderose").stop().animate({left: '-1000px'}, 400);
				$("div#rose-cont").css({"left":"-1000px"});
  			}	
		} else {
			panto = name;
	  		$(".slidepanto").stop().animate({left: '100px'}, 400);
  			if (huddisp == true) {
				var winwidth = window.document.body.clientWidth;
				var winheight = window.document.body.clientHeight;
				var left = (winwidth / 2) - 250;
				var top = (winheight / 2) - 200;
		  		$(".slidehud").stop().animate({left: left+'px'}, 400);
				$("div#hud-cont").css({"top":top+"px"});
			}
  			if (rosedisp == true) {
				var winwidth = window.document.body.clientWidth;
				var winheight = window.document.body.clientHeight;
				var left = (winwidth / 2) - 210;
				var top = (winheight / 2) - 210;
		  		$(".sliderose").stop().animate({left: left+'px'}, 400);
				$("div#rose-cont").css({"top":top+"px"});

			}

	  		markeriw.close();
			markersopen = 0;

			var div_cont = document.getElementById('panto-cont');
			div_cont.innerHTML = '<div width=330 style=\'border-style:none; overflow-x:hidden; overflow-y:hidden\'><font color=white><center><br><br><br>'+
						'<font style=\'font-family: Tahoma; font-size: 14px;\'>Now Panto Mode'+
						'</div>';

		}
	}

	function roundXL(n, digits) {
		if (digits >= 0) return parseFloat(n.toFixed(digits)); // 소수부 반올림
		digits = Math.pow(10, digits); // 정수부 반올림
		var t = Math.round(n * digits) / digits;
		return parseFloat(t.toFixed(0));
	}
					
	function pantocolor(color) {
		switch(color) {
			case 'W' :
				hudcolor = '#FFFFFF';
				break;
			case 'K' :
				hudcolor = '#414141';
				break;
			case 'R' :
				hudcolor = '#FF0000';
				break;
			case 'G' :
				hudcolor = '#51BC2A';
				break;
			case 'B' :
				hudcolor = '#0000FF';
				break;
		}		
	}
					
	function roseonoff() {
		if (huddisp == true) {
			return false;
		}	
		if (rosedisp == true) {
			rosedisp = false;
	  		$(".sliderose").stop().animate({left: '-1000px'}, 400);

		} else {
			rosedisp = true;
			var winwidth = window.document.body.clientWidth;
			var winheight = window.document.body.clientHeight;
			var left = (winwidth / 2) - 210;
			var top = (winheight / 2) - 210;
	  		$(".sliderose").stop().animate({left: left+'px'}, 400);
			$("div#rose-cont").css({"top":top+"px"});
		}	
	}					

	function hudonoff() {
		if (rosedisp == true) {
			return false;
		}	
		if (huddisp == true) {
			huddisp = false;
	  		$(".slidehud").stop().animate({left: '-1000px'}, 400);

		} else {
			huddisp = true;
	  		//$(".slidepanto").stop().animate({left: '100px'}, 400);
			var winwidth = window.document.body.clientWidth;
			var winheight = window.document.body.clientHeight;
			var left = (winwidth / 2) - 250;
			var top = (winheight / 2) - 200;
	  		$(".slidehud").stop().animate({left: left+'px'}, 400);
			$("div#hud-cont").css({"top":top+"px"});
		}	
	}					
					
	// airplane display
	function generateMarkers(locations) {
		if (markersopen == 1) return;

		if (init == 0) var ani = google.maps.Animation.DROP
		else ani = null;

		markers = [];
		cmarkers = [];
		markerlabels1 = [];
		markerlabels2 = [];
		cmarkercont = [];
		
		function addMarker(i, rmk, rmk2, x, y, ic) {
			var explode_image = new google.maps.MarkerImage(ic, new google.maps.Size(30,30), new google.maps.Point(0,0), new google.maps.Point(15,15));
			marker = new google.maps.Marker({
				map: map,
				position: new google.maps.LatLng(x, y),
				icon: explode_image,
				animation: ani,
				clickable : true,
				zindex : 9999,
				title: rmk2
			});

			google.maps.event.addListener(marker, 'click', function () {
				markeriw = new google.maps.InfoWindow();
				markeriw.setOptions({
					content: "<br>"+rmk,
					maxWidth: 450
				});
				markeriw.open(map, this);
				map.setCenter(this.getPosition());
				markersopen = 1;
				google.maps.event.addListener(markeriw,'closeclick',function() {
					markeriw.close();
					markersopen = 0;
				});
			});
			markers.push(marker);
			
		}

		members = [];
		for (var i = 0; i < locations.length; i++) {
			if (markersopen == 0) {
				addMarker(i, locations[i][0], locations[i][1], locations[i][3], locations[i][4], locations[i][5]);		//5 icon

				// display airplane label
				if (locations[i][8] == 'Unknown') {
					var rmk1 = 'Register Please...';
					var rmk2 = '';
				} else {
					var rmk1 = locations[i][8]+' ('+locations[i][9]+')';
					var rmk2 = locations[i][10]+'-'+locations[i][11];
				}
				
				if (panto == locations[i][19]) {
					var latLng = new google.maps.LatLng(locations[i][3], locations[i][4]);
					map.panTo(latLng);
					var rmk21 = rmk1 + ' - PanTo';
					rmk1 = rmk21;

					if (huddisp == true || rosedisp == true) {
						hudcolortag = '<font style=\'font-family: Tahoma; font-size: 11px; font-color: white\'>Color : '+
						'<a href=\'#\' style=\'text-decoration:none;\' onclick=\'pantocolor("W")\'><font color=black style=\'background-color:white\'>W</a></font> ' +
						'<a href=\'#\' style=\'text-decoration:none;\' onclick=\'pantocolor("K")\'><font color=white style=\'background-color:black\'>K</a></font> ' +
						'<a href=\'#\' style=\'text-decoration:none;\' onclick=\'pantocolor("R")\'><font color=white style=\'background-color:red\'>R</a></font> ' +
						'<a href=\'#\' style=\'text-decoration:none;\' onclick=\'pantocolor("G")\'><font color=white style=\'background-color:green\'>G</a></font> ' +
						'<a href=\'#\' style=\'text-decoration:none;\' onclick=\'pantocolor("B")\'><font color=white style=\'background-color:blue\'>B</a>';
					} else {
						hudcolortag = '';
					}

					var div_cont = document.getElementById('panto-cont');
					div_cont.innerHTML = '<div width=330 style=\'border-style:none; overflow-x:hidden; overflow-y:hidden\'><font color=white>'+
						'<table align=right width=330 border=0>'+

						'<tr height=12><td width=5>&nbsp;</td>'+
						'<td width=100><font style=\'font-family: Tahoma; font-size: 11px;\'>CallSign</td><td width=5>&nbsp;</td>'+
						'<td colspan=3><font style=\'font-family: Tahoma; font-size: 11px;\'>'+ locations[i][8]+' ('+locations[i][9]+')'+' / '+locations[i][13]+'</td></tr>'+

						'<tr height=12><td width=5>&nbsp;</td>'+
						'<td width=100><font style=\'font-family: Tahoma; font-size: 11px;\'>Dept/Dest</td><td width=5>&nbsp;</td>'+
						'<td colspan=3><font style=\'font-family: Tahoma; font-size: 11px;\'>'+rmk2+'</td></tr>'+

						'<tr><td colspan=6><table width="100%" cellpadding="0" cellspacing="0" border="0" align="center" style=\'border-bottom: gray solid 1px; border-top: solid 0px; border-right: solid 0px;  border-right: solid 0px;\'><tr><td></td></tr></table></td></tr>'+

						'<tr><td width=5>&nbsp;</td>'+
						'<td width=100><font style=\'font-family: Tahoma; font-size: 11px;\'>AirSpeed</td><td width=5>&nbsp;</td>'+
						'<td width=105><font style=\'font-family: Tahoma; font-size: 11px;\'>Altitude</td><td width=5>&nbsp;</td>'+
						'<td width=105><font style=\'font-family: Tahoma; font-size: 11px;\'>Heading</td></tr>'+
						'<tr><td colspan=6><table width="100%" cellpadding="0" cellspacing="0" border="0" align="center" style=\'border-bottom: gray solid 1px; border-top: solid 0px; border-right: solid 0px;  border-right: solid 0px;\'><tr><td></td></tr></table></td></tr>'+
						'<tr><td width=5>&nbsp;</td>'+
						'<td><font style=\'font-family: Tahoma; font-size: 23px;\'>'+roundXL(parseFloat(locations[i][18]), 2)+' <font style=\'font-size: 10px;\'>Knots</td><td>&nbsp;</td>'+
						'<td><font style=\'font-family: Tahoma; font-size: 23px;\'>'+commaNum(parseInt(locations[i][6]))+' <font style=\'font-size: 10px;\'>feet</td><td>&nbsp;</td>'+
						'<td><font style=\'font-family: Tahoma; font-size: 23px;\'>'+roundXL(parseFloat(locations[i][2]), 1)+' <font style=\'font-size: 10px;\'>degs</td></tr>'+
						'</table><br>'+
						'<table align=left width=320 border=0><tr><td width=10></td><td align=left width=90>' +
						'<font style=\'font-family: Tahoma; font-size: 11px; font-color: white\'>' +
						hudcolortag+
						'</td><td align=right>' +
						'<font style=\'font-family: Tahoma; font-size: 11px; font-color: white\'>' +
						'<a href=\'#\' style=\'text-decoration:none; font-color: white;\' onclick=\'roseonoff();\' title="Toggle ROSE Display"><font color=white>ROSE On/Off</a>'+
						'</td><td align=right>' +
						'<font style=\'font-family: Tahoma; font-size: 11px; font-color: white\'>' +
						'<a href=\'#\' style=\'text-decoration:none; font-color: white;\' onclick=\'hudonoff();\' title="Toggle Head Up Display"><font color=white>HUD On/Off</a>'+
						'</td><td align=right>' +
						'<font style=\'font-family: Tahoma; font-size: 11px; font-color: white\'>' +
						'<a href=\'#\' style=\'text-decoration:none; font-color: white;\' onclick=\'pantof(\"'+locations[i][19]+'\")\' title="Close PanTo mode"><font color=white>PanTo OFF</a>'+
						'</td></tr></table>'+
						'</div>';

					if (rosedisp == true) {
						div_cont = document.getElementById('rose-cont');
						div_cont.innerHTML = 
							'<svg width="420" 	height="420" version="1.1"	xmlns="http://www.w3.org/2000/svg">'+
							'<!-- 0 -->'+
							'<line x1="210" y1="180" x2="210" y2="20" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<circle cx="242" cy="33" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="275" cy="42" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="200" y="15" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">360</text>'+
							'<!-- 30 -->'+
							'<line x1="225" y1="185" x2="310" y2="45" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
							'<circle cx="330" cy="75" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="352" cy="98" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="310" y="40" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">30</text>'+
							'<!-- 60 -->'+
							'<line x1="235" y1="195" x2="377" y2="117" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
							'<circle cx="381" cy="153" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="388" cy="180" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="382" y="117" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">60</text>'+
							'<!-- 90 -->'+
							'<line x1="240" y1="210" x2="400" y2="210" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<circle cx="388" cy="240" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="380" cy="270" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="405" y="215" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">90</text>'+
							'<!-- 120 -->'+
							'<line x1="235" y1="225" x2="380" y2="305" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
							'<circle cx="352" cy="320" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="330" cy="345" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="382" y="315" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">120</text>'+
							'<!-- 150 -->'+
							'<line x1="225" y1="235" x2="310" y2="375" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
							'<circle cx="275" cy="378" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="242" cy="387" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="305" y="390" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">150</text>'+
							'<!-- 180 -->'+
							'<line x1="210" y1="240" x2="210" y2="405" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<circle cx="175" cy="387" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="147" cy="378" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="200" y="420" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">180</text>'+
							'<!-- 210 -->'+
							'<line x1="195" y1="235" x2="105" y2="375" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
							'<circle cx="91" cy="345" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="68" cy="320" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="90" y="390" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">210</text>'+
							'<!-- 240 -->'+
							'<line x1="185" y1="225" x2="45" y2="300" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
							'<circle cx="41" cy="272" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="32" cy="240" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="20" y="310" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">240</text>'+
							'<!-- 270 -->'+
							'<line x1="180" y1="210" x2="20" y2="210" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<circle cx="33" cy="179" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="40" cy="150" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="0" y="215" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">270</text>'+
							'<!-- 300 -->'+
							'<line x1="185" y1="195" x2="45" y2="115" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
							'<circle cx="70" cy="97" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="89" cy="77" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="20" y="115" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">300</text>'+
							'<!-- 330 -->'+
							'<line x1="195" y1="185" x2="110" y2="45" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
							'<circle cx="142" cy="43" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="174" cy="34" r="2" stroke="black" stroke-width="2" style="fill-opacity:1; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<text x="90" y="42"  fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">330</text>'+
							'<circle cx="210" cy="210" r="30" stroke="black" stroke-width="1" style="fill-opacity:0; stroke:'+hudcolor+'; stroke-width:1;  stroke-dasharray:5, 5;"/>'+
							'<circle cx="210" cy="210" r="180" stroke="black" stroke-width="1" style="fill-opacity:0; stroke:'+hudcolor+'; stroke-width:1;"/>'+
							'<circle cx="210" cy="210" r="90" stroke="black" stroke-width="2" style="fill-opacity:0; stroke:'+hudcolor+'; stroke-width:1; stroke-dasharray:5, 5;"/>'+
							'</svg>';
					}
				
					if (huddisp == true) {
						
					div_cont = document.getElementById('hud-cont');

					if (svg_oldhead == parseInt(locations[i][2])) {
						var svg_rmk8 = '</g>'
						var svg_x = 130;
					} else {
						if (svg_oldhead > parseInt(locations[i][2])) {
							var svg_rmk8 = '<animateMotion path="M 0 0 L 40 0" dur="2s" fill="freeze" /></g>';
							var svg_x = 90;
						} else {
							var svg_rmk8 = '<animateMotion path="M 0 0 L -40 0" dur="2s" fill="freeze" /></g>';
							var svg_x = 170;
						}	
					}
					
					
					var eleve = parseFloat(locations[i][21]);
					if (svg_oldelevation == eleve) {
						var svg_rmk9 = '</g>';
					} else {
						var svg_rmk9 = '<animateTransform attributeType="xml" attributeName="transform" type="rotate" from="'+(svg_oldelevation*-1)+' 250 200" to="'+(eleve*-1)+' 250 200" begin="0s" dur="3.5s" repeatCount="0" fill="freeze"/></g>';
					}

					var spd = parseInt(locations[i][18]);
					if (spd > 360) spd = 360;
					var alt = parseInt(locations[i][6]) / 100;
					if (alt > 360) alt = 360;

					var pitch = parseInt(locations[i][22]);
					var pitchx = pitch * 4;
					var svg_oldpitchx = svg_oldpitch * 4;

					var svg_rmk1 = '<line x1="'+svg_x+'" y1="1" x2="'+svg_x+'" y2="10" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<text x="'+(svg_x-12)+'" y="30" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">'+(parseInt(locations[i][2])-3)+'</text>';
					svg_x = svg_x + 40;
					var svg_rmk2 = '<line x1="'+svg_x+'" y1="1" x2="'+svg_x+'" y2="10" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<text x="'+(svg_x-12)+'" y="30" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">'+(parseInt(locations[i][2])-2)+'</text>';
					svg_x = svg_x + 40;
					var svg_rmk3 = '<line x1="'+svg_x+'" y1="1" x2="'+svg_x+'" y2="10" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<text x="'+(svg_x-12)+'" y="30" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">'+(parseInt(locations[i][2])-1)+'</text>';
					svg_x = svg_x + 40;
					var svg_rmk4 = '<line x1="'+svg_x+'" y1="1" x2="'+svg_x+'" y2="15" style="stroke:'+hudcolor+'; stroke-width:4"/>'+
							'<text x="'+(svg_x-15)+'" y="30" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: bold; font-size:16; fill-opacity:1;">'+(parseInt(locations[i][2])+0)+'</text>';
					svg_x = svg_x + 40;
					var svg_rmk5 = '<line x1="'+svg_x+'" y1="1" x2="'+svg_x+'" y2="10" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<text x="'+(svg_x-12)+'" y="30" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">'+(parseInt(locations[i][2])+1)+'</text>';
					svg_x = svg_x + 40;
					var svg_rmk6 = '<line x1="'+svg_x+'" y1="1" x2="'+svg_x+'" y2="10" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<text x="'+(svg_x-12)+'" y="30" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">'+(parseInt(locations[i][2])+2)+'</text>';
					svg_x = svg_x + 40;
					var svg_rmk7 = '<line x1="'+svg_x+'" y1="1" x2="'+svg_x+'" y2="10" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
							'<text x="'+(svg_x-12)+'" y="30" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:13; fill-opacity:1;">'+(parseInt(locations[i][2])+3)+'</text>';

					svg_oldhead = parseInt(locations[i][2]);
					

		         		div_cont.innerHTML = '<svg width="500" height="400">'+

		         			'<rect id="rectY" x="60" y="0" width="380" height="35" style="fill-opacity:0; stroke:'+hudcolor+'; stroke-width:1"/>'+

		         			'<rect x="0" y="0" width="50" height="20" style="fill-opacity:0; stroke:'+hudcolor+'; stroke-width:1"/>'+
		         			'<text x="12" y="15" fill="'+hudcolor+'" style="font-color:#000000; font-family:Tahoma; font-weight: bold; font-size:14; fill-opacity:1;">SPD</text>'+
		         			'<line x1="50" y1="20" x2="50" y2="400" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
		         			'<line x1="0" y1="400" x2="50" y2="400" style="stroke:'+hudcolor+'; stroke-width:1"/>'+

						'<rect rx="0" x="35" y="380" width="10" height="'+spd+'" style="fill:rgb(240, 248, 255); stroke:black; fill-opacity:1;" >'+
						'<animate attributeName="x" attributeType="XML" begin="0s" dur="3s" fill="freeze" from="35" to="35" repeatCount="1"/>'+
						'<animate attributeName="y" attributeType="XML"  begin="0s" dur="3s" fill="freeze" from="'+(380-svg_oldspd)+'" to="'+(380-spd)+'" repeatCount="1" />'+
						'<animate attributeName="height" attributeType="XML" begin="0s" dur="3s" fill="remove" from="'+svg_oldspd+'" to="'+spd+'" repeatCount="1"/>'+
						'</rect>'+

		         			'<line x1="40" y1="30" x2="50" y2="30" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="40" y1="80" x2="50" y2="80" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="40" y1="130" x2="50" y2="130" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="30" y1="180" x2="50" y2="180" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="40" y1="230" x2="50" y2="230" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="40" y1="280" x2="50" y2="280" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="40" y1="330" x2="50" y2="330" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="40" y1="380" x2="50" y2="380" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<text x="10" y="35" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">350</text>'+
		         			'<text x="10" y="85" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">300</text>'+
		         			'<text x="10" y="135" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">250</text>'+
		         			'<text x="5" y="185" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:14; fill-opacity:1;">200</text>'+
		         			'<text x="10" y="235" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">150</text>'+
		         			'<text x="10" y="285" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">100</text>'+
		         			'<text x="20" y="335" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">50</text>'+
		         			'<text x="20" y="385" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">0</text>'+
		         			'<text x="10" y="395" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">knots</text>'+



		         			'<g transform="translate(0,0)">'+
						svg_rmk1+
						svg_rmk2+
						svg_rmk3+
						svg_rmk4+
						svg_rmk5+
						svg_rmk6+
						svg_rmk7+
						svg_rmk8+

		         			'<text x="60" y="55" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">Lat:'+
		         			roundXL(parseFloat(locations[i][3]),4)+'</text>'+
		         			'<text x="360" y="55" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">Lon:'+
		         			roundXL(parseFloat(locations[i][4]),4)+'</text>'+
		         			'<text x="60" y="360" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">'+
		         			roundXL( parseFloat(locations[i][18])*1.15077945, 2)+' MPH</text>'+
		         			'<text x="60" y="375" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">'+
		         			roundXL( parseFloat(locations[i][18])*1.85200, 2)+' KPH</text>'+
		         			'<text x="380" y="360" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">'+
		         			roundXL( parseFloat(locations[i][6])*0.0003048, 2)+' KM</text>'+

		         			'<line x1="90" y1="200" x2="200" y2="200" style="stroke:'+hudcolor+'; stroke-width:1; stroke-dasharray:5, 5;"/>'+
		         			'<line x1="300" y1="200" x2="410" y2="200" style="stroke:'+hudcolor+'; stroke-width:1; stroke-dasharray:5, 5;"/>'+
		         			'<line x1="250" y1="340" x2="250" y2="360" style="stroke:'+hudcolor+'; stroke-width:1; stroke-dasharray:5, 5;"/>'+

		         			'<g transform="rotate('+(svg_oldelevation*-1)+', 250 200)">'+

	         				'<!-- line 1  -->'+
						'<text x="180" y="'+(pitchx+100)+'" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:11; fill-opacity:1;">25'+
						'	<animate attributeName="y" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+100)+'" to="'+(pitchx+100)+'" repeatCount="1" fill="freeze"/>'+
						'</text>'+
						'<text x="310" y="'+(pitchx+100)+'" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:11; fill-opacity:1;">25'+
						'	<animate attributeName="y" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+100)+'" to="'+(pitchx+100)+'" repeatCount="1" fill="freeze"/>'+
						'</text>'+
	         				'<line x1="200" y1="'+(pitchx+100)+'" x2="300" y2="'+(pitchx+100)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
	         				'	<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+100)+'" to="'+(pitchx+100)+'" repeatCount="1" fill="freeze"/>'+
	         				'	<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+100)+'" to="'+(pitchx+100)+'" repeatCount="1" fill="freeze"/>'+
	         				'</line>'+
		         			'<line x1="200" y1="'+(pitchx+100)+'" x2="200" y2="'+(pitchx+90)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
			         			'<!-- from=y1  to=y1-desc,   -->'+
			         			'<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+100)+'" to="'+(pitchx+100)+'" repeatCount="1" fill="freeze"/>'+
			         			'<!-- from=y1-10  to=y1-10-desc,   -->'+
			         			'<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+90)+'" to="'+(pitchx+90)+'" repeatCount="1" fill="freeze"/>'+
		         			'</line>	'+
		         			'<line x1="300" y1="'+(pitchx+100)+'" x2="300" y2="'+(pitchx+90)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
			         			'<!-- from=y1  to=y1-desc,   -->'+
			         			'<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+100)+'" to="'+(pitchx+100)+'" repeatCount="1" fill="freeze"/>'+
			         			'<!-- from=y1-10  to=y1-10-desc,   -->'+
			         			'<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+90)+'" to="'+(pitchx+90)+'" repeatCount="1" fill="freeze"/>'+
		         			'</line>'+

	         				'<!-- line 2  -->'+
						'<text x="190" y="'+(pitchx+150)+'" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:11; fill-opacity:1;">12.5'+
						'	<animate attributeName="y" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+150)+'" to="'+(pitchx+150)+'" repeatCount="1" fill="freeze"/>'+
						'</text>'+
						'<text x="290" y="'+(pitchx+150)+'" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:11; fill-opacity:1;">12.5'+
						'	<animate attributeName="y" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+150)+'" to="'+(pitchx+150)+'" repeatCount="1" fill="freeze"/>'+
						'</text>'+

	         				'<line x1="220" y1="'+(pitchx+150)+'" x2="280" y2="'+(pitchx+150)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
	         				'	<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+150)+'" to="'+(pitchx+150)+'" repeatCount="1" fill="freeze"/>'+
	         				'	<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+150)+'" to="'+(pitchx+150)+'" repeatCount="1" fill="freeze"/>'+
	         				'</line>'+
		         			'<line x1="220" y1="'+(pitchx+150)+'" x2="220" y2="'+(pitchx+140)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
			         			'<!-- from=y1  to=y1-desc,   -->'+
			         			'<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+150)+'" to="'+(pitchx+150)+'" repeatCount="1" fill="freeze"/>'+
			         			'<!-- from=y1-10  to=y1-10-desc,   -->'+
			         			'<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+140)+'" to="'+(pitchx+140)+'" repeatCount="1" fill="freeze"/>'+
		         			'</line>	'+
		         			'<line x1="280" y1="'+(pitchx+150)+'" x2="280" y2="'+(pitchx+140)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
			         			'<!-- from=y1  to=y1-desc,   -->'+
			         			'<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+150)+'" to="'+(pitchx+150)+'" repeatCount="1" fill="freeze"/>'+
			         			'<!-- from=y1-10  to=y1-10-desc,   -->'+
			         			'<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+140)+'" to="'+(pitchx+140)+'" repeatCount="1" fill="freeze"/>'+
		         			'</line>'+

  						'<polygon points="205,'+(pitchx+195)+' 205,'+(pitchx+205)+' 210,'+(pitchx+200)+'" style="fill:'+hudcolor+'; stroke:'+hudcolor+'; stroke-width:1">'+
	         				'	<animate attributeName="points" attributeType="XML" dur="3s" '+
	         							'from="205,'+(svg_oldpitchx+195)+' 205,'+(svg_oldpitchx+205)+' 210,'+(svg_oldpitchx+200)+'" '+
	         							'to="205,'+(pitchx+195)+' 205,'+(pitchx+205)+' 210,'+(pitchx+200)+'" repeatCount="1" fill="freeze"/>'+
  						'</polygon>'+

  						'<polygon points="295,'+(pitchx+195)+' 295,'+(pitchx+205)+' 290,'+(pitchx+200)+'" style="fill:'+hudcolor+'; stroke:'+hudcolor+'; stroke-width:1">'+
	         				'	<animate attributeName="points" attributeType="XML" dur="3s" '+
	         							'from="295,'+(svg_oldpitchx+195)+' 295,'+(svg_oldpitchx+205)+' 290,'+(svg_oldpitchx+200)+'" '+
	         							'to="295,'+(pitchx+195)+' 295,'+(pitchx+205)+' 290,'+(pitchx+200)+'" repeatCount="1" fill="freeze"/>'+
  						'</polygon>'+

		         			
	         				'<!-- line 3  -->'+
						'<text x="185" y="'+(pitchx+255)+'" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:11; fill-opacity:1;">-12.5'+
						'	<animate attributeName="y" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+255)+'" to="'+(pitchx+255)+'" repeatCount="1" fill="freeze"/>'+
						'</text>'+
						'<text x="285" y="'+(pitchx+255)+'" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:11; fill-opacity:1;">-12.5'+
						'	<animate attributeName="y" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+255)+'" to="'+(pitchx+255)+'" repeatCount="1" fill="freeze"/>'+
						'</text>'+
	         				'<line x1="220" y1="'+(pitchx+250)+'" x2="280" y2="'+(pitchx+250)+'" style="stroke:'+hudcolor+'; stroke-width:1; stroke-dasharray:4, 4;">'+
	         				'	<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+250)+'" to="'+(pitchx+250)+'" repeatCount="1" fill="freeze"/>'+
	         				'	<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+250)+'" to="'+(pitchx+250)+'" repeatCount="1" fill="freeze"/>'+
	         				'</line>'+
		         			'<line x1="220" y1="'+(pitchx+250)+'" x2="220" y2="'+(pitchx+260)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
			         			'<!-- from=y1  to=y1-desc,   -->'+
			         			'<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+250)+'" to="'+(pitchx+250)+'" repeatCount="1" fill="freeze"/>'+
			         			'<!-- from=y1-10  to=y1-10-desc,   -->'+
			         			'<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+260)+'" to="'+(pitchx+260)+'" repeatCount="1" fill="freeze"/>'+
		         			'</line>	'+
		         			'<line x1="280" y1="'+(pitchx+250)+'" x2="280" y2="'+(pitchx+260)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
			         			'<!-- from=y1  to=y1-desc,   -->'+
			         			'<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+250)+'" to="'+(pitchx+250)+'" repeatCount="1" fill="freeze"/>'+
			         			'<!-- from=y1-10  to=y1-10-desc,   -->'+
			         			'<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+260)+'" to="'+(pitchx+260)+'" repeatCount="1" fill="freeze"/>'+
		         			'</line>'+

	         				'<!-- line 4  -->'+
						'<text x="175" y="'+(pitchx+305)+'" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:11; fill-opacity:1;">-25'+
						'	<animate attributeName="y" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+305)+'" to="'+(pitchx+305)+'" repeatCount="1" fill="freeze"/>'+
						'</text>'+
						'<text x="305" y="'+(pitchx+305)+'" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:11; fill-opacity:1;">-25'+
						'	<animate attributeName="y" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+305)+'" to="'+(pitchx+305)+'" repeatCount="1" fill="freeze"/>'+
						'</text>'+

	         				'<line x1="200" y1="'+(pitchx+300)+'" x2="300" y2="'+(pitchx+300)+'" style="stroke:'+hudcolor+'; stroke-width:1; stroke-dasharray:4, 4;">'+
	         				'	<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+300)+'" to="'+(pitchx+300)+'" repeatCount="1" fill="freeze"/>'+
	         				'	<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+300)+'" to="'+(pitchx+300)+'" repeatCount="1" fill="freeze"/>'+
	         				'</line>'+
		         			'<line x1="200" y1="'+(pitchx+300)+'" x2="200" y2="'+(pitchx+310)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
			         			'<!-- from=y1  to=y1-desc,   -->'+
			         			'<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+300)+'" to="'+(pitchx+300)+'" repeatCount="1" fill="freeze"/>'+
			         			'<!-- from=y1-10  to=y1-10-desc,   -->'+
			         			'<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+310)+'" to="'+(pitchx+310)+'" repeatCount="1" fill="freeze"/>'+
		         			'</line>	'+
		         			'<line x1="300" y1="'+(pitchx+300)+'" x2="300" y2="'+(pitchx+310)+'" style="stroke:'+hudcolor+'; stroke-width:1;">'+
			         			'<!-- from=y1  to=y1-desc,   -->'+
			         			'<animate attributeName="y1" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+300)+'" to="'+(pitchx+300)+'" repeatCount="1" fill="freeze"/>'+
			         			'<!-- from=y1-10  to=y1-10-desc,   -->'+
			         			'<animate attributeName="y2" attributeType="XML" dur="3s" from="'+(svg_oldpitchx+310)+'" to="'+(pitchx+310)+'" repeatCount="1" fill="freeze"/>'+
		         			'</line>'+

		         			
		         			'<line x1="90" y1="200" x2="200" y2="200" style="stroke:'+hudcolor+'; stroke-width:2;"/>'+
		         			'<line x1="300" y1="200" x2="410" y2="200" style="stroke:'+hudcolor+'; stroke-width:2;"/>'+
		         			'<line x1="250" y1="340" x2="250" y2="360" style="stroke:'+hudcolor+'; stroke-width:2;"/>'+
		         			'<line x1="250" y1="90" x2="250" y2="180" style="stroke:'+hudcolor+'; stroke-width:2; stroke-dasharray:5, 5;"/>'+
		         			'<circle cx="250" cy="200" r="150" stroke="black" stroke-width="2" style="fill-opacity:0; stroke:'+hudcolor+'; stroke-width:1; stroke-dasharray:5, 5;"/>'+

		         			svg_rmk9+
		         			'<text x="210" y="75" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:14; fill-opacity:1;">'+
		         			roundXL(svg_oldelevation,3)+' deg</text>'+
		         			'<text x="210" y="375" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">Pitch:'+
		         			roundXL(parseFloat(locations[i][22]),3)+'</text>'+

		         			'<rect x="450" y="0" width="50" height="20" style="fill-opacity:0; stroke:'+hudcolor+'; stroke-width:1;"/>'+
		         			'<text x="460" y="15" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: bold; font-size:14; fill-opacity:1;">'+
		         			'ALT</text>'+
		         			'<line x1="450" y1="20" x2="450" y2="400" style="stroke:'+hudcolor+'; stroke-width:1"/>'+
		         			'<line x1="450" y1="400" x2="500" y2="400" style="stroke:'+hudcolor+'; stroke-width:1"/>'+

						'<rect rx="0" x="455" y="380" width="10" height="'+alt+'" style="fill:rgb(240, 248, 255); stroke:black; fill-opacity:1;" >'+
						'<animate attributeName="x" attributeType="XML" begin="0s" dur="3s" fill="freeze" from="455" to="455" repeatCount="1"/>'+
						'<animate attributeName="y" attributeType="XML"  begin="0s" dur="3s" fill="freeze" from="'+(380-svg_oldalt)+'" to="'+(380-alt)+'" repeatCount="1" />'+
						'<animate attributeName="height" attributeType="XML" begin="0s" dur="3s" fill="remove" from="'+svg_oldalt+'" to="'+alt+'" repeatCount="1"/>'+
						'</rect>'+


		         			'<line x1="450" y1="30" x2="460" y2="30" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="450" y1="80" x2="460" y2="80" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="450" y1="130" x2="460" y2="130" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="450" y1="180" x2="470" y2="180" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="450" y1="230" x2="460" y2="230" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="450" y1="280" x2="460" y2="280" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="450" y1="330" x2="460" y2="330" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<line x1="450" y1="380" x2="460" y2="380" style="stroke:'+hudcolor+'; stroke-width:2"/>'+
		         			'<text x="470" y="35" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">350</text>'+
		         			'<text x="470" y="85" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">300</text>'+
		         			'<text x="470" y="135" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">250</text>'+
		         			'<text x="475" y="185" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:14; fill-opacity:1;">200</text>'+
		         			'<text x="470" y="235" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">150</text>'+
		         			'<text x="470" y="285" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">100</text>'+
		         			'<text x="470" y="335" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">50</text>'+
		         			'<text x="470" y="380" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">x100</text>'+
		         			'<text x="470" y="395" fill="'+hudcolor+'" style="font-family:Tahoma; font-weight: normal; font-size:12; fill-opacity:1;">feet</text>'+

		         			'<text x="130" y="395" fill="'+hudcolor+'" style="font-family:Tahoma; font-size:9; stoke:rgb(0,255,0)" align="left">'+
		         			'Copyright 2016. All Rights Reserved.'+
		         			'</text>'+
		         			'</svg>';
		         			svg_oldelevation = eleve;
		         			svg_oldspd = spd;
		         			svg_oldalt = alt;
		         			svg_oldpitch = pitch;
		         			}

				}
				
				if (rmk1 == 'Register Please...') {
					var sicon = new google.maps.MarkerImage('http://chart.googleapis.com/chart?chst=d_text_outline&chld=FF0000|10|l|FFFFFF|_|'+rmk1, null, null, new google.maps.Point(15, -14));
				} else {
					var sicon = new google.maps.MarkerImage('http://chart.googleapis.com/chart?chst=d_text_outline&chld=000000|10|l|FFFFFF|_|'+rmk1, null, null, new google.maps.Point(15, -14));
				}
				makerlabel1 = new google.maps.Marker({
					map: map,
					position: new google.maps.LatLng(locations[i][3], locations[i][4]),
					icon: sicon
				});
				markerlabels1.push(makerlabel1);
				sicon = new google.maps.MarkerImage('http://chart.googleapis.com/chart?chst=d_text_outline&chld=000000|10|l|FFFFFF|_|'+rmk2, null, null, new google.maps.Point(15, -24));
				makerlabel2 = new google.maps.Marker({
					map: map,
					position: new google.maps.LatLng(locations[i][3], locations[i][4]),
					icon: sicon
				});
				markerlabels2.push(makerlabel2);
				
				// display memberlist
				if (locations[i][10] == '') {
					var dest = 'Free';
				} else {
					var dest = '<a href=\"javascript:sendpath(\''+locations[i][10]+'\', \''+locations[i][11]+'\');\" style=\'text-decoration:none\' title=\''+locations[i][15]+' - '+locations[i][16]+'\'>'+locations[i][10]+'-'+locations[i][11]+'</a>';
				}
				var dest2 = '';
				if (locations[i][14] == 'IFR') {
					var dest2 = '<br>[IFR] ';
				}
				if (locations[i][14] == 'VFR') {
					var dest2 = '<br>[VFR] ';
				}

				var flyflag = false;
				if (locations[i][12] == 'HC') {
					if (locations[i][20] <= 5) {
						dest2 = dest2 + '<font color=black>GND</font>';
					} else {
						flyflag = true;
						dest2 = dest2 + '<font color=#0066CC><b>FLY..</B></font>';
					}
				} else {
					if (locations[i][12] == 'PA') {
						if (locations[i][18] > 90) {
							dest2 = dest2 + '<font color=#0066CC><b>FLY..</B></font>';
							flyflag = true;
						} else {
							if (locations[i][20] <= 2) {
								dest2 = dest2 + '<font color=black>GND</font>';
							} else {
								if (locations[i][20] <= 30) {
									dest2 = dest2 + '<font color=#2EB300><b>TAXI.</font>';
								} else {
									dest2 = dest2 + '<font color=#2EB300><b>Roll.</font>';
								}
							}
						}
					} else {
						if (locations[i][12] == 'GA') {
							if (locations[i][18] > 80) {
								dest2 = dest2 + '<font color=#0066CC><b>FLY..</B></font>';
								flyflag = true;
						} else {
								if (locations[i][20] <= 2) {
									dest2 = dest2 + '<font color=black>GND</font>';
								} else {
									if (locations[i][20] <= 25) {
										dest2 = dest2 + '<font color=#2EB300><b>TAXI.</font>';
									} else {
										dest2 = dest2 + '<font color=#2EB300><b>Roll.</font>';
									}
								}
							}
						} else {
							if (locations[i][18] > 110) {
								dest2 = dest2 + '<font color=#0066CC><b>FLY..</B></font>';
								flyflag = true;
						} else {
								if (locations[i][20] <= 2) {
									dest2 = dest2 + '<font color=black>GND</font>';
								} else {
									if (locations[i][20] <= 30) {
										dest2 = dest2 + '<font color=#2EB300><b>TAXI.</font>';
									} else {
										dest2 = dest2 + '<font color=#2EB300><b>Roll.</font>';
									}
								}
							}
						}	
					}	
				}

				cmarkercont[i] = parseInt(locations[i][18]);
				
				if (dispcircle == true) {
					var smiter = (((parseInt(locations[i][18]) * 1.852) * 1000) / 60) * 2;
					if (flyflag == false) smiter = 0;
					if (rmk1 == 'Register Please...')  smiter = 0;

    					// Add the circle 
					var cmarkerOptions = {
      					strokeColor: '#F79F50',
	      				strokeOpacity: 0.7,
      					strokeWeight: 1,
      					fillColor: '#FFD346',
		      			fillOpacity: 0.1,
      					map: map,
      					center: new google.maps.LatLng(locations[i][3], locations[i][4]),
						clickable : true,
						infoWindowIndex: i,
	     					radius: smiter
					};
					cmarker = new google.maps.Circle(cmarkerOptions);
					
					
					google.maps.event.addListener(cmarker, 'click', (function(cmarker, i) { return function(ev) {
						var ssmiter = (((cmarkercont[i] * 1.852) * 1000) / 60) * 2;
						var iwc = new google.maps.InfoWindow();
						iwc.setOptions({
    							content: i+"Current Air Speed is <b>"+cmarkercont[i]+"</b> Knots<br>Radious is <b>"+roundXL(ssmiter/1609.344, 2)+"</b> mi ("+roundXL(ssmiter, 2)+"</b> miters)<br><br>"
    									+"The size of the circle will arrive within 1 minute away<br>(원의 크기 : 현재속도로 1분안에 도착거리)"
	    					});
    						iwc.setPosition(ev.latLng);
    						iwc.open(map);
					}
					}) (cmarker, i) );
				
					cmarkers.push(cmarker);
				} else {
					//cmarkers[i].setMap(null);
				}


				if (locations[i][8] == 'Unknown') {
					var dest3 = '';
					continue;
				} else {
					var dest3 = '<a href=\'javascript:earth_path(\"'+locations[i][19]+'\", '+locations[i][3]+', '+locations[i][4]+');\' style=\'text-decoration:none\' title=\'View Google Earth\'>'+
							'<img src=\'http://220.230.100.125/~ash/xplane/images/earth.png\' title=\'\' border=0></a>';
				}

				var bgcol = (i % 2) ? '#FAFAFA' : '#F7F7F7';
				members[i] = '<tr><td height=32 valign=middle><table border=0 style=\"background-color:'+bgcol+';\"><tr><td width=90>'+
				'<a href=\'javascript:gotoMembers('+locations[i][3]+','+locations[i][4]+');\' style=\'text-decoration:none\' title=\'Zoom In\'>'+locations[i][8]+'<br>'+locations[i][9]+'</a>'+
				'<td width=7>:</td><td width=85>'+
				dest+dest2+'</td><td width=35>'+
				'<a href=\'javascript:viewfootpath(\"'+locations[i][19]+'\", '+locations[i][3]+','+locations[i][4]+');\' style=\'text-decoration:none\' title=\'Toggle Footpath view\'>'+
				'<img src=\''+locations[i][5]+'\' title=\'\' border=0></a></td><td width=45>'+
				locations[i][13]+
				'</td><td width=24>'+dest3+
				'</td></tr></table></td></tr>';
			}
		}

		if (members.length <= 0) clearMembers();
		else generateMembers(members);
		init = 1;
	}

	// This function takes an array argument containing a list of marker data
	function generatePathMarkers(i, x, y, name) {
		var iconimg = new google.maps.MarkerImage("http://220.230.100.125/~ash/xplane/images/ap.png");
		iconimg.size = new google.maps.Size(16, 16);
		iconimg.anchor = new google.maps.Point(8, 8);
//		if (i == 0) sicon = new google.maps.MarkerImage("http://chart.googleapis.com/chart?chst=d_bubble_text_small&chld=bb|"+name+"|FFB573|000000", null, null, new google.maps.Point(0, 42))
//		else	if (i == 999) sicon = new google.maps.MarkerImage("http://chart.googleapis.com/chart?chst=d_bubble_text_small&chld=bb|"+name+"|C6EF8C|000000", null, null, new google.maps.Point(0, 42))
		if (i == 0) sicon = new google.maps.MarkerImage("http://chart.googleapis.com/chart?chst=d_simple_text_icon_below&chld="+name+"|12|000000|location|16|FFFFFF|FFB573")
		else	if (i == 999) sicon = new google.maps.MarkerImage("http://chart.googleapis.com/chart?chst=d_simple_text_icon_below&chld="+name+"|12|000000|location|16|FFFFFF|559930")
		else var sicon = new google.maps.MarkerImage("http://chart.googleapis.com/chart?chst=d_simple_text_icon_below&chld="+name+"|11|000000|location|16|FFFFFF|FF8080");
		pathmarker = new google.maps.Marker({
				map: map,
				position: new google.maps.LatLng(x, y),
				//icon: iconimg,
//			      icon: new google.maps.MarkerImage("http://chart.googleapis.com/chart?chst=d_bubble_text_small&chld=bb|"+name+"|FF8080|000000", null, null, new google.maps.Point(0, 42))
			      icon: sicon

			});
			pathmarkers.push(pathmarker);
	}

	function tatct() {
		if (toggle_actc == 1) {
			for (i = 0; i < apmarkers.length; i++) apmarkers[i].setVisible(false);
			toggle_actc = 0;
			document.getElementById("btn_airport").value = "Show AP";

		} else {
			for (i = 0; i < apmarkers.length; i++) apmarkers[i].setVisible(true);
			toggle_actc = 1;
			document.getElementById("btn_airport").value = "Hide AP";
		}
	}

	function clearOverlays() {
		clearMembers();
		for (var i = 0; i < markers.length; i++ ) {
			if (markersopen == 0) {
				markers[i].setMap(null);
				markerlabels1[i].setMap(null);
				markerlabels2[i].setMap(null);
			}
		}
		for (var i = 0; i < cmarkers.length; i++ ) {
			cmarkers[i].setMap(null);
		}
		members = [];
		members.length = 0;
	}

	function zoominap(x,y) {
		prevzoomlevel = map.getZoom();
		map.setZoom(15);
		map.setCenter(new google.maps.LatLng(x, y));
	}

	function zoominair(x,y) {
		prevzoomlevel = map.getZoom();
		map.setZoom(15);
		map.setCenter(new google.maps.LatLng(x, y));
	}

	function zoomprev() {
		map.setZoom(prevzoomlevel);
	}

	function viewregion(val) {
		switch(val) {
			case '0' :
				map.setZoom(2);
				map.setCenter(new google.maps.LatLng(37.55, 150.88));
				break;
			case '1' :
				map.setZoom(7);
				map.setCenter(new google.maps.LatLng(36.05, 128.04));
				break;
			case '2' :
				map.setZoom(6);
				map.setCenter(new google.maps.LatLng(39.45, 137.28));
				break;
			case '3' :
				map.setZoom(5);
				map.setCenter(new google.maps.LatLng(35.18, 111.28));
				break;
			case '4' :
				map.setZoom(5);
				map.setCenter(new google.maps.LatLng(20.41, 78.52));
				break;
			case '5' :
				map.setZoom(5);
				map.setCenter(new google.maps.LatLng(39.54202, -99.4334));
				break;
			case '6' :
				map.setZoom(7);
				map.setCenter(new google.maps.LatLng(20.44, -156.43));
				break;
			case '7' :
				map.setZoom(7);
				map.setCenter(new google.maps.LatLng(65.54, -150.23));
				break;
			case '8' :
				map.setZoom(5);
				map.setCenter(new google.maps.LatLng(56.28, -110.44));
				break;
			case '9' :
				map.setZoom(5);
				map.setCenter(new google.maps.LatLng(-12.59, -59.20));
				break;
			case '10' :
				map.setZoom(5);
				map.setCenter(new google.maps.LatLng(-23.44, 135.20));
				break;
			case '11' :
				map.setZoom(5);
				map.setCenter(new google.maps.LatLng(48.38, 6.40));
				break;
			case '12' :
				map.setZoom(4);
				map.setCenter(new google.maps.LatLng(9.32, 20.38));
				break;
		}
	}

	function showfixLabel(k, str) {
		var arrString = str.split("|");
		var j = parseInt(k);
		fixlabelmarkers[j] = [];
		fixlabelmarkers2[j] = [];

		for (var i = 0; i < arrString.length; i++) {
			var str1 = arrString[i].split(",");
			var sicon = new google.maps.MarkerImage('http://chart.googleapis.com/chart?chst=d_text_outline&chld=000000|10|h|FFFFFF|_|'+str1[2], null, null, new google.maps.Point(15, 17));
			fixlabelmarker = new google.maps.Marker({
				map: map,
				position: new google.maps.LatLng(str1[1], str1[0]),
				icon: sicon
			});
			fixlabelmarkers[j].push(fixlabelmarker);

			sicon = new google.maps.MarkerImage('http://chart.googleapis.com/chart?chst=d_text_outline&chld=FFFFFF|10|h|5D7ECD|_|'+str1[5], null, null, new google.maps.Point(10, 5));
			fixlabelmarker2 = new google.maps.Marker({
				map: map,
				position: new google.maps.LatLng( str1[7], str1[6]),
				icon: sicon
			});
			fixlabelmarkers2[j].push(fixlabelmarker2);

		}
	}
	
	function togglewp(svalue) {
		var URL, i;

		if (svalue == '999') {
			for (i = 0; i < wpLayer.length; i++) {
				if (wpLayer[i] != null) {
					wpLayer[i].setMap(null);
					wpLayer[i] = null;
					for (var j = 0; j < fixlabelmarkers[i].length; j++) 
						fixlabelmarkers[i][j].setVisible(false);
					fixlabelmarkers[i] = [];			
					for (var j = 0; j < fixlabelmarkers2[i].length; j++) 
						fixlabelmarkers2[i][j].setVisible(false);
					fixlabelmarkers2[i] = [];			
				}
			}
		} else {
			var k = parseInt(svalue);
			if (wpLayer[k] == null) {
				URL ='http://220.230.100.125/~ash/xplane/waypoint/waypoint_'+svalue+'.kmz?dummy='+Math.round(new Date().getTime());
				wpLayer[k] = new google.maps.KmlLayer ( URL, {preserveViewport:true});
				wpLayer[k].setMap(map);

				var str = $.get("getlabel.php",  { id1: svalue },
					function(data) {
						if (data.substring(0,3) == "ERR") {
							alert("Unknown fix label data");
							return;
						}
						showfixLabel(svalue, data);}, "html"
					)
				.error(function() { alert("Path Find Server Error !"); })
				
			}	
		}	
	}

	function togglehighwind() {
		if (ctaLayer2.getMap()) {
			ctaLayer2.setMap(null);
		} else {
			if (ctaLayer1.getMap()) {
				ctaLayer1.setMap(null);
				ctaLayer2.setMap(map);
			} else {
				ctaLayer1.setMap(map);
			}
		}
	}

	function toggleclouds() {
		if (cloudLayer.getMap()) {
			cloudLayer.setMap(null);
			document.getElementById("btn_cloud").value = "Show Clouds";
		} else {
			cloudLayer.setMap(map);
			document.getElementById("btn_cloud").value = "Hide Clounds";
		}
	}

	function toggleweather() {
		if (weatherLayer.getMap()) {
			weatherLayer.setMap(null);
			document.getElementById("btn_weather").value = "Show Weather";
		} else {
			weatherLayer.setMap(map);
			document.getElementById("btn_weather").value = "Hide Weather";
		}
	}

	function clearpath() {
		for (i = 0; i < pathmarkers.length; i++) {
			pathmarkers[i].setMap(null);
		}

		for (i = 0; i < line.length; i++) {
			line[i].setMap(null); //or line[i].setVisible(false);
		}
		document.getElementById("id1").value = "";
		document.getElementById("id2").value = "";
	}

	function sendpath(id1, id2) {
		document.getElementById("id1").value = id1;
		document.getElementById("id2").value = id2;
	}

	function clearpathpopup() {
		var div_cont = document.getElementById('findpath-cont');
         	div_cont.innerHTML = '<center>'
         				         	+'<div style=\"position:absolute; left:330px; top:5px; width: 24px; height:24px; border-width:1px; border-style:none;\">'
         						+'<img src=\'http://220.230.100.125/~ash/xplane/images/close_24.png\'></div>'
         						+'<font style=\'font-family: Tahoma; font-size: 14px;\'>'
							+'<br><br>Welcome !<br><br><br>Rader Map Live Air Traffic Path Finder !';
	}

	function popup1(cont) {
		var div_cont = document.getElementById('findpath-cont');
         	div_cont.innerHTML = '<center></center>'
					         	+'<div style=\"position:absolute; left:330px; top:5px; width: 24px; height:24px; border-width:1px; border-style:none;\">'
         						+'<img src=\'http://220.230.100.125/~ash/xplane/images/close_24.png\'></div>'
         						+'<font style=\'font-family: Tahoma; font-size: 12px;\'>'
         						+'<div style=\'overflow-y:scroll; width:340px; height:500px; padding:10px;\'>'
         						+cont
         						+'<br></div><center><input type=\'button\'  id=\'btn_about\' style=\'font: 12px Tahoma; width: 80px;\' onclick=\'clearpathpopup()\' value=\'Clear Path\'></input>';

		$(".slide").click();
	}

	function closeright() {
		cl = 1;
	}

	function closeearthpath() {
		clearth = 1;
	}

	var today = new Date();
	var expiry = new Date(today.getTime() + 30 * 24 * 3600 * 1000); // plus 30 days

	function getCookie(c_name) {
		var c_value = document.cookie;
		var c_start = c_value.indexOf(" " + c_name + "=");
		if (c_start == -1) {
			c_start = c_value.indexOf(c_name + "=");
		}
		if (c_start == -1) {
			c_value = null;
		} else {
			c_start = c_value.indexOf("=", c_start) + 1;
			var c_end = c_value.indexOf(";", c_start);
			if (c_end == -1) {
				c_end = c_value.length;
			}
			c_value = unescape(c_value.substring(c_start,c_end));
		}
		return c_value;
	}

	function setCookie(name, value)  {
		document.cookie=name + "=" + escape(value) + "; path=/; expires=" + expiry.toGMTString();
	}

	function registerplan() {
		var pv1 = $("input:text[id=pcallsign]").val();
		var pv2 = $("input:text[id=pname]").val();
		var pv3 = $("input:text[id=pd1]").val();
		var pv4 = $("input:text[id=pd2]").val();
		var pv5 = $("select[id=pmodel]").val();
		var pv6 = $("input:text[id=pmodelname]").val();
		var pv7 = $("select[id=ptype]").val();

		$.post("register.php",
			{"pcallsign": pv1,
			"pname": pv2,
			"pd1": pv3,
			"pd2": pv4,
			"pmodel": pv5,
			"pmodelname": pv6,
			"ptype": pv7
			},
			function(req) {
				alert(req);
			}
		);
		setCookie('pcallsign', pv1);
		setCookie('pname', pv2);
		setCookie('pmodelname', pv6);
		setCookie('pd1', pv3);
		setCookie('pd2', pv4);
	}

	function helpudp() {
		var div_cont = document.getElementById('helpudp-cont');
		// 이미 세팅된 주소를 가져옴
		var str;
		var jqxhr = $.get('http://220.230.100.125/~ash/xplane/helpudp.html', function(data) {
					div_cont.innerHTML = data;
				});

		$(".slidetop").click();
	}

	function popup2(cont) {
		var div_cont = document.getElementById('findpath-cont-right');
         	div_cont.innerHTML = '<div style=\"position:absolute; left:5px; top:5px; width: 24px; height:24px; border-width:1px; border-style:none;\">'
         						+'<img src=\'http://220.230.100.125/~ash/xplane/images/close_24.png\' onclick=\'closeright()\'></div>'
         						+'<center></center><font style=\'font-family: Tahoma; font-size: 12px;\'>'
         						+'<div style=\'width:340px; height:200px; padding-left:15px; padding-top:5px;\'>'
         						+'&nbsp;<b>Information :</b><br>&nbsp;Your registration information will be displayed on the map<br>'
         						+'&nbsp;Please register every flight. <b><a href=\'#\' onclick=\'helpudp()\'>Click here</b></a> to use<br>'
         						+'<table border=0 width=330>'
							+'<tr height=5><td width=80></td><td></td></tr>'
           						+'<tr><td width=80>'
         						+'CallSign :</td><td width=250>'
         						+'<INPUT maxLength=15 size=15 id=\'pcallsign\' value=\'\' style=\'text-transform: uppercase;\'></td></tr>'
         						+'<tr><td>Nickname :</td><td>'
         						+'<INPUT maxLength=15 size=15 id=\'pname\' value=\'\' ></td></tr>'
							+'<tr><td>Model :</td><td>'
							+'<select id=\'pmodel\'>'
							+'<option value=\"HM\">Heavy Metal</option>'
							+'<option value=\"PA\">Prop Airliner</option>'
  							+'<option value=\"BJ\">Business Jet</option>'
  							+'<option value=\"GA\">General Aviation</option>'
							+'<option value=\"HC\">Helicopter</option>'
  							+'<option value=\"FT\">Fighter</option>'
							+'</select><INPUT maxLength=15 size=8 id=\'pmodelname\' value=\'\'></td></tr>'
							+'<tr><td>Flight Type :</td><td>'
							+'<select id=\'ptype\'>'
							+'<option value=\"IFR\">IFR</option>'
							+'<option value=\"VFR\">VFR</option>'
							+'</select></td></tr>'
         						+'<tr><td>Departure :</td><td>'
         						+'<INPUT maxLength=4 size=4 id=\'pd1\' value=\'\' style=\'text-transform: uppercase;\'>'
							+'&nbsp; Destination : '
							+'<INPUT maxLength=4 size=4 id=\'pd2\' value=\'\' style=\'text-transform: uppercase;\'></td></tr>'
         						+'</table>'
         						+'</div><center>'

         						+'<div id=\'butons\' style=\'width:310px; height:50px; padding-left:0px; padding-top:00px;\'>'
         						+'<input type=\'button\' id=\'btn_register\' style=\'font: 12px Tahoma; width: 80px;\' onclick=\'registerplan()\' value=\'Register\'></input>&nbsp;'
         						+'<input type=\'button\' id=\'btn_close\' style=\'font: 12px Tahoma; width: 80px;\' onclick=\'closeright()\' value=\'Close\'></input>'
         						+'<hr size=1 width=300><b>Registered Members</b></center>'
         						+'</div>'

         						+'<div id=\'viewmembers\' style=\'overflow-y:scroll; width:330px; height:290px; padding-left:20px; padding-top:0px;\'>'
         						+'</div>';
		document.getElementById('pcallsign').value = getCookie("pcallsign");
		document.getElementById('pname').value = getCookie("pname");
		document.getElementById('pmodelname').value = getCookie("pmodelname");
		document.getElementById('pd1').value = getCookie("pd1");
		document.getElementById('pd2').value = getCookie("pd2");
		document.getElementById('pmodel').focus();
	}

	function showRoute(str){
		var arrString = str.split("|");
		var flightPlanCoordinates = [];

		if (arrString[0] == 'ERR') {
			popup1(arrString[1]+"<br>Please search again by changing the Cycle<br><br>입력하신 구간의 자료가 없습니다.<br>Cycle 을 변경하여 다시 검색하세요.");
			return false;
		} else {
		popup1(arrString[1]);
		for (i = 2; i < arrString.length; i++) {
			var str3 = arrString[i];
			var str4 = str3.split(",");
			if (i == 2) generatePathMarkers(0, str4[0], str4[1], str4[3]);
			if (i == arrString.length-1) generatePathMarkers(999, str4[0], str4[1], str4[3]);
			else generatePathMarkers(i-2, str4[0], str4[1], str4[3]);
			flightPlanCoordinates[i-2] = new google.maps.LatLng(str4[0], str4[1]);
		}

		var flightPath = new google.maps.Polyline({
			path: flightPlanCoordinates,
			strokeColor: "#FF0000",
			strokeOpacity: 1.0,
			strokeWeight: 2
		});
		flightPath.setMap(map);
		line.push(flightPath);

		bounds = new google.maps.LatLngBounds();
		for (var i=0; i < flightPlanCoordinates.length; i++) {
			bounds.extend(flightPlanCoordinates[i]);
		}
		map.fitBounds(bounds);
		}
	}

	function showRoute2(str){
	         
		var arrString = str.split("|");
		var flightPlanCoordinates = [];

		if (arrString[0] == 'ERR') {
			popup1(arrString[1]+"<br>Please search again by changing the Cycle<br><br>입력하신 구간의 자료가 없습니다.<br>Cycle 을 변경하여 다시 검색하세요.");
			return false;
		} else {
		popup1(arrString[1]);
		for (i = 2; i < 4; i++) {
			var str3 = arrString[i];
			var str4 = str3.split(",");
			if (i == 2) generatePathMarkers(0, str4[0], str4[1], str4[3]);
			if (i == 3) generatePathMarkers(999, str4[0], str4[1], str4[3]);
			flightPlanCoordinates[i-2] = new google.maps.LatLng(str4[0], str4[1]);
		}

		var flightPath = new google.maps.Polyline({
			path: flightPlanCoordinates,
			strokeColor: "#FF0000",
			strokeOpacity: 1.0,
			strokeWeight: 2
		});
		flightPath.setMap(map);
		line.push(flightPath);

		bounds = new google.maps.LatLngBounds();
		for (var i=0; i < flightPlanCoordinates.length; i++) {
			bounds.extend(flightPlanCoordinates[i]);
		}
		map.fitBounds(bounds);
		}
	}

	function findpath() {
		var vid1 = document.getElementById("id1").value;
		var vid2 = document.getElementById("id2").value;
		var vid3 = document.getElementById("id3").value;

		if (vid1.length < 3 || vid2.length < 3) {
			alert("Error ICAO Code !");
			return;
		}
		var str = $.get("getpath.php",  { id1: vid1, id2: vid2, id3: vid3 },
				function(data) {
					showRoute(data);}, "html"
				)
		.error(function() { alert("Path Find Server Error ! "); })
	}

	function findpath2() {
		var vid1 = document.getElementById("id1").value;
		var vid2 = document.getElementById("id2").value;
		var vid3 = document.getElementById("id3").value;

		if (vid1.length < 3 || vid2.length < 3) {
			alert("Error ICAO Code !");
			return;
		}
		var str = $.get("viewpath.php",  { id1: vid1, id2: vid2, id3: vid3 },
				function(data) {
					showRoute2(data);}, "html"
				)
		.error(function() { alert("Path Find Server Error ! "); })
	}	

	function showFoot(str){
		var flightFootCoordinates = [];

		for (i = 0; i < footline.length; i++) {
			footline[i].setMap(null); //or line[i].setVisible(false);
		}

		for (i = 0; i < str.length; i++) {
			flightFootCoordinates[i] = new google.maps.LatLng(str[i][1], str[i][2]);
		}

		var flightfootPath = new google.maps.Polyline({
			path: flightFootCoordinates,
			strokeColor: "#0000FF",
			strokeOpacity: 1.0,
			strokeWeight: 2,
			zindex: 11
		});
		flightfootPath.setMap(map);
		footline.push(flightfootPath);

		//map.setZoom(6);
		//map.setCenter(new google.maps.LatLng(str4[0], str4[1]));
	}

	function viewfootpath(vid1, x, y) {
		gotoMembers(x, y);
		if (footline.length > 0) {
			erasefootpath();
		} else {
		var str = $.get("getfoot.php",  { ipaddr: vid1 },
				function(data) {
					if (data.length == 0) {
						alert("Unknown Data");
						return;
					}
					showFoot(data);}, "json"
				)
		.error(function() { alert("Path Find Server Error ! "); })
		}
	}

	function eraseapeye() {
  		for (i = 0; i < circles.length; i++) {
			circles[i].setMap(null);
		}
		apcitymap = {};
	}

	function erasefootpath() {
  		for (i = 0; i < footline.length; i++) {
			footline[i].setMap(null);
		}
		footline = [];
	}

	function eyevisual(name, x, y, kmeter) {
		if (kmeter <= 0) return false;
		viewmeter = kmeter * 1000;
		viewapeye(name, x, y, viewmeter);	// view Visibility circle
	}

	function viewapeye(apid, x, y, miter) {
		if (apcitymap[apid]) return;
		apcitymap[apid] = {
			center: new google.maps.LatLng(x, y),
			eye: miter
		};

		var eyeOptions = {
      			strokeColor: '#AA0000',
	      		strokeOpacity: 0.8,
      			strokeWeight: 1,
      			fillColor: '#990000',
	      		fillOpacity: 0.1,
      			map: map,
      			center: apcitymap[apid].center,
      			radius: apcitymap[apid].eye
		};
    		// Add the circle for this city to the map.
		var cityCircle = new google.maps.Circle(eyeOptions);
		circles.push(cityCircle);
	}

	function liveatc(icao, urlname, desc) {
		$(".slideliveatc").click();
		var div_cont = document.getElementById('liveatc-cont');
		if (atcdir) {
	         	div_cont.innerHTML = '<img src=\'http://220.230.100.125/~ash/xplane/images/liveatc1.png\' width=95 height=30>'+
      	   					'<iframe src=\'http://www.liveatc.net/flisten.php?mount='+urlname+'&icao='+icao+'\' style=\'width:0px; height:0px; border: 0px\'></iframe>'+
      	   					'<font style=\'font-family: Tahoma; font-size: 11px;\'>'+
      	   					'<br>&nbsp;'+icao+' Play...wait<br>&nbsp;Click to Stop';
		} else {
	         	div_cont.innerHTML = '<iframe src=\'about:blank\' style=\'width:0px; height:0px; border: 0px\'></iframe>';
		}
	}

	function toggleliveatc() {
		if (toggle_liveactc == 1) {
			for (i = 0; i < liveatcmarkers.length; i++) liveatcmarkers[i].setVisible(false);
			toggle_liveactc = 0;
			document.getElementById("btn_liveatc").value = "Show ATC";

		} else {
			readliveatc();
			toggle_liveactc = 1;
			document.getElementById("btn_liveatc").value = "Hide ATC";
		}
	}

	function readliveatc() {
		liveatcicao = [];
		function addLiveATC(i, x, y, icao, urlname, desc) {
        			var latlng = new google.maps.LatLng(
        						parseFloat(x),
        			            	parseFloat(y));
				liveatcmarker = new google.maps.Marker({
					position: latlng,
				      clickable : true,
					map: map,
					icon : 'http://220.230.100.125/~ash/xplane/images/atcap.png',
					title: desc+' ('+icao+')'
				});

				google.maps.event.addListener(liveatcmarker, 'click', function () {
					var iw = new google.maps.InfoWindow();
					iw.setOptions({
							content: '<img src=\'http://220.230.100.125/~ash/xplane/images/liveatc1.png\' width=95 height=30><br>'+
      	   						'<font style=\'font-family: Tahoma; font-size: 11px;\'><b>'+desc+' ('+icao+')</b><br>'+
								'<input type=\'button\' id=\'btn_playatc\' style=\'font: 12px Tahoma; width: 180px;\' onclick=\"javascript:liveatc(\''+icao+'\', \''+urlname+'\', \''+desc+'\');\" value=\'Play/Stop Live ATC\'></input><br>',
							maxWidth: 400
					});
					iw.open(map, this);
					map.setCenter(this.getPosition());
					google.maps.event.addListener(iw,'closeclick',function() {
						iw.close();
					});
				});

			liveatcmarkers.push(liveatcmarker);
			toggle_liveactc = 1;
		}

		downloadUrl("http://220.230.100.125/~ash/xplane/liveatc.xml", function(data) {
			var atcmark = data.documentElement.getElementsByTagName("Airport");
      		for (var i = 0; i < atcmark.length; i++) {
				var icao = data.documentElement.getElementsByTagName("ICAO")[i].textContent;
				var x = data.documentElement.getElementsByTagName("Latitude")[i].textContent;
				var y = data.documentElement.getElementsByTagName("Longitude")[i].textContent;
				var desc = data.documentElement.getElementsByTagName("Description")[i].textContent;
				var urlname = data.documentElement.getElementsByTagName("URLName")[i].textContent;
				liveatcicao[icao] = urlname;

				addLiveATC(i, x, y, icao, urlname, desc);
			}
     		});
	}

	
	function about() {
		alert("Welcome !\n");
	}

	function showEarth(ip1, x, y) {
		var div_cont = document.getElementById('earth-cont');
		div_cont.innerHTML = '<img src=\'http://220.230.100.125/~ash/drone/images/close_24.png\' onclick=\'closeearthpath()\'><br><center>'+
						'<div width=800 style=\'border-style:none; overflow-x:hidden; overflow-y:hidden\'>'+
						'<iframe id=\'iframeearth\' style=\'width:820px; height:550px;\' frameborder=no></iframe>'+
						'</div>';
		document.getElementById('iframeearth').src = 'http://220.230.100.125/~ash/xplane/earth.html?ipaddr='+ip1+'&x='+x+'&y='+y;
	}

	function earth_path(vid1, x, y) {
		$(".slideearth").click();
		var str = $.get("te.php",  { ipaddr: vid1 },
				function(data) {
					if (data == 'ERROR') {
						alert("Unknown Data");
						return;
					}
					}, "html"
				)
		.error(function() { alert("Google Earth Find Server Error ! "); })

		var str = $.get("tepath2.php",  { ipaddr: vid1 },
				function(data) {
					if (data == 'ERROR') {
						alert("Unknown Data");
						return;
					}
					showEarth(vid1, x, y);}, "html"
				)
		.error(function() { alert("Google Earth Find Server Error ! "); })
	}

	function togglefunc(sval) {
		switch(sval) {
			case 'SP1' :
	            	dispcircle = true; 
				break;
			case 'SP2' :
	            	dispcircle = false; 
				break;
			case 'A1' :
				map.setZoom(2);
				mapDayNightShadow = function(map, UTCTime, minutesOffset) {
					if (dn == null) {
						dn = new DayNightMapType(UTCTime, minutesOffset);
		            		map.overlayMapTypes.insertAt(0, dn);
		            		dn.setMap(map);
			            	dn.setAutoRefresh(600);	// 600sec
		      	      	dn.setShowLights(1);
		      	  	} else {
						dn.calcCurrentTime(UTCTime, minutesOffset);
						dn.redoTiles();
					}
				}
				var now = new Date(); 
				var now_utc = new Date(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(),  now.getUTCHours(), now.getUTCMinutes(), now.getUTCSeconds());
				mapDayNightShadow(map, now, 0);
				break;
			case 'A2' :
	            	map.overlayMapTypes.setAt(0, null); 
	            	dn = null;
				break;
			case 'B1' :
				if (!ctaLayer.getMap()) ctaLayer.setMap(map);
				break;
			case 'B2' :
				if (ctaLayer.getMap()) ctaLayer.setMap(null);
				break;
			case 'C1' :
				if (toggle_liveactc == 1) {
				} else {
					readliveatc();
					toggle_liveactc = 1;
				}
				break;
			case 'C2' :
				if (toggle_liveactc == 1) {
					for (i = 0; i < liveatcmarkers.length; i++) liveatcmarkers[i].setVisible(false);
					toggle_liveactc = 0;
				}
				break;
			case 'Y1' :
				window.open("http://www.x-plane.kr", "", "");
				break;
			case 'Z1' :
				window.open("http://www.gudun.co.kr/wizhtml.php?html=NetFlight%20Korea%20Map", "", "");
				break;
			case 'Z2' :
				about();
				break;
		}		
	}
	
	window.onresize = resize;
	function resize() {
		var winwidth=document.all?document.body.clientWidth:window.innerWidth;
		var winheight=document.all?document.body.clientWidth:window.innerHeight;
		
		if (!rdir) {
			var left = winwidth - 20;
			$("div#findpath-cont-right").css({"left":left+"px"});
		} else {
			var left = winwidth - 20 - 340;
			$("div#findpath-cont-right").css({"left":left+"px"});
		}
		
		if (huddisp == true &&  $("div#hud-cont").position().left > 0 ) {
			var left = (winwidth / 2) - 250;
			var top = (winheight / 2) - 200;
			$("div#hud-cont").css({"top":top+"px"});
			$("div#hud-cont").css({"left":left+"px"});
		}
		
	}
