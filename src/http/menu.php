<?php

require_once(dirname(__FILE__) . "/../common.php");

function menuHttp()
{
	if($GLOBALS["menuComponent"] == "http") {
		return <<<HTML
<li>
<a href="{$GLOBALS["rootHtml"]}http/">Web hosting</a>
<ul>
<li><a href="{$GLOBALS["rootHtml"]}http/adddomain.php">New domain</a></li>
</ul>
</li>

HTML;
	} else {
		return "<li><a href=\"{$GLOBALS["rootHtml"]}http/\">Web hosting</a></li>\n";
	}
}

if(canAccessComponent("http", true)) {
	addMenu("menuHttp");
} else if(canAccessComponent("http")) {
	addAdminMenu("menuHttp");
}

?>