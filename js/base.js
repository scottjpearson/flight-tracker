function stripFromHTML(str, html) {
	html = stripHTML(html);
	var lines = html.split(/\n/);
	var regex = new RegExp(str+":\\s+(.+)$", "i");
	var matches;
	for (var i=0; i < lines.length; i++) {
		var line = lines[i];
		if (matches = line.match(regex)) {
			if (matches[1]) {
				return matches[1];
			}
		}
	}
	return "";
}

function turnOffStatusCron() {
	$.post(getPageUrl("testConnectivity.php"), { turn_off: 1 }, function(html) {
		console.log("Turned off "+html);
		$("#status").html("Off");
		$("#status_link").html("Turn on status cron");
		$("#status_link").attr("onclick", "turnOnStatusCron();");
	});
}

function turnOnStatusCron() {
	$.post(getPageUrl("testConnectivity.php"), { turn_on: 1 }, function(html) {
		console.log("Turned on "+html);
		$("#status").html("On");
		$("#status_link").html("Turn off status cron");
		$("#status_link").attr("onclick", "turnOffStatusCron();");
	});
}

function trimPeriods(str) {
	return str.replace(/\.$/, "");
}

function submitPMC(pmc, textId) {
	if (pmc) {
		if (!pmc.match(/PMC/)) {
			pmc = "PMC"+pmc;
		}
		var url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pmc&retmode=xml&id="+pmc;
		$.ajax({
			url: url,
			success: function(xml) {
				var pmid = "";
				$(xml).find("pmc-articleset>article>front>article-meta>article-id").each(function() {
					if ($(this).attr("pub-id-type") == "pmid") {
						pmid = "PubMed PMID: "+$(this).text()+". ";
					}
				});
				var journal = "";
				$(xml).find("pmc-articleset>article>front>journal-meta>journal-id").each(function() {
					if ($(this).attr("journal-id-type") == "iso-abbrev") {
						journal = $(this).text();
					}
				});
				journal = journal.replace(/\.$/, "");

				var year = "";
				var month = "";
				var day = "";
				$(xml).find("pmc-articleset>article>front>article-meta>pub-date").each(function() {
					var pubType = $(this).attr("pub-type");
					if ((pubType == "collection") || (pubType == "ppub")) {
						if ($(this).find("month")) {
							month = $(this).find("month").text();
						}
						if ($(this).find("year")) {
							year = $(this).find("year").text();
						}
						if ($(this).find("day")) {
							day = " "+$(this).find("day").text();
						}
					}
				});
				var volume = $(xml).find("pmc-articleset>article>front>article-meta>volume").text();
				var issue = $(xml).find("pmc-articleset>article>front>article-meta>issue").text();

				var fpage = $(xml).find("pmc-articleset>article>front>article-meta>fpage").text();
				var lpage = $(xml).find("pmc-articleset>article>front>article-meta>lpage").text();
				var pages = "";
				if (fpage && lpage) {
					pages = fpage + "-" + lpage;
				}

				var title = $(xml).find("pmc-articleset>article>front>article-meta>title-group>article-title").text();
				title = title.replace(/\.$/, "");

				var names = [];
				$(xml).find("pmc-articleset>article>front>article-meta>contrib-group>contrib").each(function(index, elem) {
					if ($(elem).attr("contrib-type") == "author") {
						var surname = $(elem).find("name>surname").text();
						var givenNames = $(elem).find("name>given-names").text();
						names.push(surname+" "+givenNames);
					}
				});

				pmc = pmc + ".";
				var loc = getLocation(volume, issue, pages);
				var citation = names.join(",")+". "+title+". "+journal+". "+year+" "+month+day+";"+loc+". "+pmid+pmc;
				$(textId).val(citation);

			},
			error: function(e) {
				$(textId).html("ERROR: "+JSON.stringify(e));
			}
		});
	}
}
function getLocation(volume, issue, pages) {
	var loc = volume;
	if (issue) {
		loc += "("+issue+")";
	}
	if (pages) {
		loc += ":"+pages;
	}
	return loc;
}

function submitPMID(pmid, textId) {
	if (pmid) {
		var url = "https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?db=pubmed&retmode=xml&id="+pmid;
		$.ajax({
			url: url,
			success: function(xml) {
				// similar to publications/getPubMedByName.php
				// make all changes in two places in two languages!!!

				var year = $(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>Journal>JournalIssue>PubDate>Year").text();
				var month = $(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>Journal>JournalIssue>PubDate>Month").text();
				var volume = $(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>Journal>JournalIssue>Volume").text();
				var issue = $(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>Journal>JournalIssue>Issue").text();
				var pages = $(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>Pagination>MedlinePgn").text();
				var title = $(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>ArticleTitle").text();
				title = title.replace(/\.$/, "");

				var journal = trimPeriods($(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>Journal>ISOAbbreviation").text());
				journal = journal.replace(/\.$/, "");

				var dayNode = $(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>Journal>JournalIssue>PubDate>Day");
				var day = "";
				if (dayNode) {
					day = " "+dayNode.text();
				}

				var names = [];
				$(xml).find("PubmedArticleSet>PubmedArticle>MedlineCitation>Article>AuthorList>Author").each(function(index, elem) {
					var lastName = $(elem).find("LastName");
					var initials = $(elem).find("Initials");
					var collective = $(elem).find("CollectiveName");
					if (lastName && initials) {
						names.push(lastName.text()+" "+initials.text());
					} else if (collective) {
						names.push(collective.text());
					}
				});

				var loc = getLocation(volume, issue, pages);
				var citation = names.join(",")+". "+title+". "+journal+". "+year+" "+month+day+";"+loc+". PubMed PMID: "+pmid+".";
				$(textId).val(citation);
			},
			error: function(e) {
				$(textId).html("ERROR: "+JSON.stringify(e));
			}
		});
	}
}

// returns PHP timestamp: number of seconds (not milliseconds)
function getPubTimestamp(citation) {
	if (!citation) {
		return 0;
	}
	var nodes = citation.split(/[\.\?]\s+/);
	var date = "";
	var i = 0;
	var issue = "";
	while (!date && i < nodes.length) {
		if (nodes[i].match(/;/) && nodes[i].match(/\d\d\d\d.*;/)) {
			var a = nodes[i].split(/;/);
			date = a[0];
			issue = a[1];
		}       
		i++;   
	}       
	if (date) {
		var dateNodes = date.split(/\s+/);

		var year = dateNodes[0];
		var month = "";
		var day = "";

		var months = { "Jan": "01", "Feb": "02", "Mar": "03", "Apr": "04", "May": "05", "Jun": "06", "Jul": "07", "Aug": "08", "Sep": "09", "Oct": "10", "Nov": "11", "Dec": "12" };

		if (dateNodes.length == 1) {
			month = "01";
		} else if (!isNaN(dateNodes[1])) {
			month = dateNodes[1];
			if (month < 10) {
				month = "0"+parseInt(month);
			}       
		} else if (months[dateNodes[1]]) {
			month = months[dateNodes[1]];
		} else {
			month = "01";
		}       
		
		if (dateNodes.length <= 2) {
			day = "01";
		} else {
			day = dateNodes[2];
			if (day < 10) {
				day = "0"+parseInt(day);
			}       
		}       
		var datum = new Date(Date.UTC(year,month,day,'00','00','00'));
		return datum.getTime()/1000;
	} else {
		return 0;
	}
}

function stripOptions(html) {
	return html.replace(/<option[^>]*>[^<]*<\/option>/g, "");
}

function stripBolds(html) {
	return html.replace(/<b>.+<\/b>/g, "");
}

function stripButtons(html) {
	return html.replace(/<button.+<\/button>/g, "");
}

function refreshHeader() {
	var numCits = $("#center div.notDone").length;
	if (numCits == 1) {
		$(".newHeader").html(numCits + " New Citation");
	} else if (numCits === 0) {
		$(".newHeader").html("No New Citations");
	} else {
		$(".newHeader").html(numCits + " New Citations");
	}
}

function sortCitations(html) {
	var origCitations = html.split(/\n/);
	var timestamps = [];
	for (var i = 0; i < origCitations.length; i++) {
		timestamps[i] = getPubTimestamp(stripHTML(stripBolds(stripButtons(origCitations[i]))));
	}
	for (var i = 0; i < origCitations.length; i++) {
		var citationI = origCitations[i];
		var tsI = timestamps[i];
		for (j = i; j < origCitations.length; j++) {
			var citationJ = origCitations[j];
			var tsJ = timestamps[j];
			if (tsI < tsJ) {
				// switch
				origCitations[j] = citationI;
				origCitations[i] = citationJ;

				citationI = citationJ;
				tsI = tsJ;
				// Js will be reassigned with the next iteration of the j loop
			}
		}
	}
	return origCitations.join("\n")+"\n";
}

function stripHTML(str) {
	return str.replace(/<[^>]+>/g, "");
}

function removeThisElem(elem) {
	$(elem).remove();
	refreshHeader();
}

function omitCitation(citation) {
	var html = $("#omitCitations").html();
	var citationHTML = "<div class='finalCitation'>"+citation+"</div>";
	html += citationHTML;

	$("#omitCitations").html(sortCitations(html));
}

function getPMID(citation) {
	var matches = citation.match(/PubMed PMID: \d+/);
	if (matches && (matches.length >= 1)) {
		var pmidStr = matches[0];
		var pmid = pmidStr.replace(/PubMed PMID: /, "");
		return pmid;
	}
	return "";
}

function getMaxID() {
	return $("#newCitations .notDone").length;
}

function addCitationLink(citation) {
	return citation.replace(/PubMed PMID: (\d+)/, "<a href='https://www.ncbi.nlm.nih.gov/pubmed/?term=$1'>PubMed PMID: $1</a>");
}

function getUrlVars() {
	var vars = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
		vars[key] = value;
	});
	return vars;
}

function refresh() {
	location.reload();
}

// page is blank if current page is requested
function getPageUrl(page) {
	var params = getUrlVars();
	if (params['page']) {
		var url = "?pid="+params['pid'];
		if (page) {
			page = page.replace(/\.php$/, "");
			url += "&page="+encodeURIComponent(page);
		} else if (params['page']) {
			url += "&page="+encodeURIComponent(params['page']);
		}
		if (params['prefix']) {
			url += "&prefix="+encodeURIComponent(params['prefix']);
		}
		return url;
	}
	return page;
}

function getHeaders() {
	var params = getUrlVars();
	if (typeof params['headers'] != "undefined") {
		return "&headers="+params['headers'];
	}
	return "";
}

function getPid() {
	var params = getUrlVars();
	if (typeof params['pid'] != "undefined") {
		return params['pid'];
	}
	return "";
}

function makeNote(note) {
	if (typeof note != "undefined") {
		$("#note").html(note);
		if ($("#note").hasClass("green")) {
			$("#note").removeClass("green");
		}
		if (!$("#note").hasClass("red")) {
			$("#note").addClass("red");
		}
	} else {
		if ($("#note").hasClass("red")) {
			$("#note").removeClass("red");
		}
		if (!$("#note").hasClass("green")) {
			$("#note").addClass("green");
		}
		$("#note").html("Save complete! Please <a href='javascript:;' onclick='refresh();'>refresh</a> to see the latest list after you have completed your additions.");
	}
	$("#note").show();
}

// coordinated with Citation::getID in class/Publications.php
function getID(citation) {
	var matches;
	if (matches = citation.match(/PMID: \d+/)) {
		var pmidStr = matches[0];
		return pmidStr.replace(/^PMID: /, "");
	} else {
		return citation;
	}
}

function isCitation(id) {
	if (id) {
		if (id.match(/^PMID/)) {
			return true;
		}
		if (id.match(/^ID/)) {
			return true;
		}
	}
	return false;
}

function isOriginal(id) {
	if (id) {
		if (id.match(/^ORIG/)) {
			return true;
		}
	}
	return false;
}

function getRecord() {
	var params = getUrlVars();
	var recordId = params['record'];
	if (typeof recordId == "undefined") {
		return 1;
	}
	return recordId;
}

function submitChanges(nextRecord) {
	var recordId = getRecord();
	var newFinalized = [];
	var newOmits = [];
	var resets = [];
	$('#finalize').hide();
	$('#uploading').show();
	$('[type=hidden]').each(function(idx, elem) {
		var id = $(elem).attr("id");
		if ((typeof id != "undefined") && id.match(/^PMID/)) {
			var value = $(elem).val();
			var pmid = id.replace(/^PMID/, "");
			if (!isNaN(pmid)) {
				if (value == "include") {
					// checked => put in finalized
					newFinalized.push(pmid);
				} else if (value == "exclude") {
					// unchecked => put in omits
					newOmits.push(pmid);
				} else if (value == "reset") {
					resets.push(pmid);
				}
			}
		}
	});
	var url = getPageUrl("wrangler/savePubs.php");
	$.ajax({
		url: url,
		method: 'POST',
		data: {
			record_id: recordId,
			omissions: JSON.stringify(newOmits),
			resets: JSON.stringify(resets),
			finalized: JSON.stringify(newFinalized)
		},
		dataType: 'json',
		success: function(data) {
			if (data['count'] && (data['count'] > 0)) {
				var str = "items";
				if (data['item_count'] == 1) {
					str = "item";
				}
				var mssg = data['count']+" "+str+" uploaded";
				window.location.href = getPageUrl("wrangler/pubs.php")+getHeaders()+"&mssg="+encodeURI(mssg)+"&record="+nextRecord;
			} else if (data['item_count'] && (data['item_count'] > 0)) {
				var str = "items";
				if (data['item_count'] == 1) {
					str = "item";
				}
				var mssg = data['item_count']+" "+str+" uploaded";
				window.location.href = getPageUrl("wrangler/pubs.php")+getHeaders()+"&mssg="+encodeURI(mssg)+"&record="+nextRecord;
			} else if (data['error']) {
				$('#uploading').hide();
				$('#finalize').show();
				alert('ERROR: '+data['error']);
			} else {
				$('#uploading').hide();
				$('#finalize').show();
				console.log("Unexplained return value. "+JSON.stringify(data));
			}
		},
		error: function(e) {
			if (!e.status || (e.status != 200)) {
				$('#uploading').hide();
				$('#finalize').show();
				alert("ERROR: "+JSON.stringify(e));
			} else {
				console.log(JSON.stringify(e));
			}
		}
	});
	console.log("Done");
}

function checkSticky() {
	var normalOffset = $('#normalHeader').offset();
	if (window.pageYOffset > normalOffset.top) {
		if (!$('#stickyHeader').is(':visible')) {
			$('#stickyHeader').show();
			$('#stickyHeader').width($('maintable').width()+"px");
			$('#stickyHeader').css({ "left": $('#maintable').offset().left+"px" });
		}
	} else {
		if ($('#stickyHeader').is(':visible')) {
			$('#stickyHeader').hide();
		}
	}
}

function submitOrder(selector, resultsSelector) {
	if ($(resultsSelector).hasClass("green")) {
		$(resultsSelector).removeClass("green");
	}
	if ($(resultsSelector).hasClass("red")) {
		$(resultsSelector).removeClass("red");
	}
	$(resultsSelector).addClass("yellow");
	$(resultsSelector).html("Processing...");
	$(resultsSelector).show();

	var keys = new Array();
	$(selector+" li").each(function(idx, ob) {
		var id = $(ob).attr("id");
		keys.push(id);
	});
	if (keys.length > 0) {
		$.post(getPageUrl("lexicallyReorder.php"), { keys: JSON.stringify(keys) }, function(data) {
			console.log("Done");
			console.log(data);
			$(resultsSelector).html(data);
			if ($(resultsSelector).hasClass("yellow")) {
				$(resultsSelector).removeClass("yellow");
			}
			if (data.match(/ERROR/)) {
				$(resultsSelector).addClass("red");
			} else {
				$(resultsSelector).addClass("green");
			}
			setTimeout(function() {
				$(resultsSelector).fadeOut();
			}, 5000);
		});
	}
}

function presentScreen(mssg) {
	if ($('#overlay').length > 0) {
		$('#overlay').html('<br><br><br><br><h1 class=\"warning\">'+mssg+'</h1>');
		$('#overlay').show();
	}
}

function clearScreen() {
	if ($('#overlay').length > 0) {
		$('#overlay').html('');
		$('#overlay').hide();
	}
}

function toggleHelp(helpUrl, helpHiderUrl, currPage) {
	if ($('#help').is(':visible')) {
		hideHelp(helpHiderUrl);
	} else {
		showHelp(helpUrl, currPage);
	}
}

function showHelp(helpUrl, currPage) {
	$.post(helpUrl, { fullPage: currPage }, function(html) {
		if (html) {
			$('#help').html(html);
		} else {
			$('#help').html("<h4 class='nomargin'>No Help Resources are Available for This Page</h4>");
		}
		// coordinate with .subnav
		if ($('.subnav').length == 1) {
			var right = $('.subnav').position().left + $('.subnav').width(); 
			var offset = 10;
			var helpLeft = right + offset;
			var rightOffset = helpLeft + 40;
			var helpWidth = "calc(100% - "+rightOffset+"px)";
			$('#help').css({ left: helpLeft+"px", position: "relative", width: helpWidth });
		} 
		$('#help').slideDown();
	});
}

function hideHelp(helpHiderUrl) {
	$('#help').hide();
	$.post(helpHiderUrl, { }, function() {
	});
}

function startTonight() {
	var url = getPageUrl("downloadTonight.php");
	console.log(url);
	$.ajax({
		url:url,
		success: function(data) {
			console.log("result: "+data);
			alert("Downloads will start tonight!");
		},
		error: function(e) {
			console.log("ERROR! "+JSON.stringify(e));
		}
	});
}

function installMetadata(fields) {
	var url = getPageUrl("metadata.php");
	$("#metadataWarning").removeClass("red");
	$("#metadataWarning").addClass("yellow");
	$("#metadataWarning").html("Installing...");
	$.post(url, { process: "install", fields: fields }, function(data) {
		console.log(JSON.stringify(data));
		$("#metadataWarning").removeClass("yellow");
		if (!data.match(/Exception/)) {
			$("#metadataWarning").addClass("green");
			$("#metadataWarning").html("Installation Complete!");
			setTimeout(function() {
				$("#metadataWarning").fadeOut(500);
			}, 3000);
		} else {
			$("#metadataWarning").addClass("red");
			$("#metadataWarning").html("Error in installation! Metadata not updated. "+JSON.stringify(data));
		}
	});
}

function checkMetadata(phpTs) {
	var url = getPageUrl("metadata.php");
	$.post(url, { process: "check", timestamp: phpTs }, function(html) {
		if (html) {
			$('#metadataWarning').addClass("red");
			$('#metadataWarning').html(html);
		}
	});
}

function submitLogs(url) {
	$.post(url, {}, function(data) {
		console.log(data);
		alert("Emailed logs to Developers");
	});
}
