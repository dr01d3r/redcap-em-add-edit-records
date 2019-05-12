$(function() {
    if (OrcaSearch) {
        $addEditLink = $("#west a:contains(" + OrcaSearch.addEditLinkText + ")");
        switch (OrcaSearch.moduleLinkType) {
            case "add_edit_replace":
                $addEditLink.attr("href", OrcaSearch.orcaSearchURL);
                $("<span class='orca-tooltip' data-toggle='popover' data-content='This link has been modified to send you to the Orca Search Module'>\n" +
                    "\t<i class='fas fa-info-circle' style='display: inline;color: #800000;'></i>\n" +
                    "</span>").insertAfter($addEditLink);
                break;
        }
    }
    $('.orca-tooltip').popover({
        container: 'body',
        trigger: 'hover'
    });
});