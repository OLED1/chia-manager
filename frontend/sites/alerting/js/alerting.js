$(function(){
    adaptServiceTypeWidth();
});
var custom_rules_tosave = {};
var alerting_rule_tosave = {};

initWarnAndCritInputs();
initRestorePreviousDefault();
initSaveRuleChange();
initRemoveRule();
initAlertingTypesDropdown();
initHelpRule();
initEditAlertingRule();
resetSetupAlertingNodeTabs();
initHelpAlertingRule();

$("#setup-alerting-tabs .nav-link").first().addClass("active");
$("#setup-node-alerting-pane .tab-pane").first().addClass("show active");

$("#custom-rule-host-tabs .nav-link").first().addClass("active");
$("#custom-alerting-pane .tab-pane").first().addClass("show active");

$("#configure-alerting-services-tabs .nav-link").first().addClass("active");
$("#configure-alerting-services-pane .tab-pane").first().addClass("show active");

function resetSetupAlertingNodeTabs(){
    $("#setup-alerting-node-tabs .nav-link").removeClass("active").first().addClass("active");
    $("#setup-alerting-node-pane .tab-pane").removeClass("show active").first().addClass("show active");

    $("#setup-alerting-tab").on("click", function(){
        adaptServiceTypeWidth();
    });
    
    $(".setup-alerting-node-tab").on("click", function(){
        adaptServiceTypeWidth();
    });
}


$("#enable_mailing").on("click", function(){
    data = {
        "alerting_service_id" : $("#enable_mailing").attr("data-service-id"),
        "enabled" : $("#enable_mailing").is(":checked")
    };

    window.sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "enableService", data);
});

$(".custom-rules-node-tab").on("click", function(){
    adaptServiceTypeWidth();
});

function initHelpRule(){
    $(".help-rule").off("click");
    $(".help-rule").on("click", function(){
        var rule_id = $(this).closest(".rule").attr("data-rule-id");
        var target_rule_data = alerting_rules["by_rule_id"][rule_id];

        $("#help_modal_rule_id").text(rule_id);
        $("#help_modal_rule_system_type").text((target_rule_data["rule_default"] == 1 ? "Default" : "Custom (Overwrites default rule for this node)"));
        $(".help_modal_node_target").text((target_rule_data["system_target"] == 1 ? "All nodes (default rule)" : target_rule_data["hostname"]));
        $(".help_modal_service_type").text(target_rule_data["service_desc"]);
        $("#help_modal_service_target").text((target_rule_data["rule_target"] === null ? "None" : target_rule_data["rule_target"]));
        $("#help_modal_rule_warn_level").text(target_rule_data["perc_or_min_value"] == 0 ? "When " + target_rule_data["warn_at_after"] + "% usage reached." : "After a total downtime of " + target_rule_data["warn_at_after"] + " minutes.");
        $("#help_modal_rule_crit_level").text(target_rule_data["perc_or_min_value"] == 0 ? "When " + target_rule_data["crit_at_after"] + "% usage reached." : "After a total downtime of " + target_rule_data["crit_at_after"] + " minutes.");

        $("#help_modal_text_warn_level").text(target_rule_data["perc_or_min_value"] == 0 ? "at a total usage of " + target_rule_data["warn_at_after"] + "%" : "at a total downtime of " + target_rule_data["warn_at_after"] + " minutes");
        $("#help_modal_text_crit_level").text(target_rule_data["perc_or_min_value"] == 0 ? "when a total usage of " + target_rule_data["crit_at_after"] + "% are reached." : "when the total downtime has exceeded " + target_rule_data["crit_at_after"] + " minutes.");
        $("#help_modal_text_service_target").text(target_rule_data["rule_default"] == 1 ? "This is a default rule and does not target a per-system specific service" : "This is a custom rule which overwrites the default rule for this system. It covers the node specific service '" + target_rule_data["rule_target"] + "'");

        $("#rule_help_modal").modal("show");
    });
}

function initHelpAlertingRule(){
    $(".help-alerting-rule").off("click");
    $(".help-alerting-rule").on("click", function(){
        var node_id = $(this).attr("data-node-id");
        var rule_id = $(this).attr("data-rule-id");
        var target_rule = alerting_rules["by_rule_id"][rule_id];

        $("#alerting-services-info-container").animate({ scrollTop: 0 }, "fast");
        $("#configured_rule_alerting_rule_id").text(rule_id);
        $("#configured_rule_alerting_system_type").text((target_rule["rule_default"] == 1 ? "Default" : "Custom (Overwrites default rule for this node)"));
        $("#configured_rule_alerting_target").text((target_rule["system_target"] == 1 ? "All nodes (default rule)" : target_rule["hostname"]));
        $("#configured_rule_alerting_service_type").text(target_rule["service_desc"]);
        $("#configured_rule_alerting_service_target").text((target_rule["rule_target"] === null ? "None" : target_rule["rule_target"]));
        $("#configured_rule_alerting_warn_level").text(target_rule["perc_or_min_value"] == 0 ? "When " + target_rule["warn_at_after"] + "% usage reached." : "After a total downtime of " + target_rule["warn_at_after"] + " minutes.");
        $("#configured_rule_alerting_crit_level").text(target_rule["perc_or_min_value"] == 0 ? "When " + target_rule["crit_at_after"] + "% usage reached." : "After a total downtime of " + target_rule["crit_at_after"] + " minutes.");
        
        if(rule_id in available_setup_alertings["by_rule_id"] && node_id in available_setup_alertings["by_rule_id"][rule_id]){    
            $.each(alerting_services, function(alerting_service_id, alerting_service_data){
                var target_service = $(".alerting-service-information[data-alerting-service-id=" + alerting_service_id + "]"); 

                var warn_alert_after_text = "Never";
                var warn_alert_after_bg_color = "bg-danger";
                var crit_alert_after_text = "Never";
                var crit_alert_after_bg_color = "bg-danger";

                target_service.find(".alerting-info-alerts-to-recepients li").remove();
                target_service.find(".alerting-info-alerts-name").text("");

                if(alerting_service_id in available_setup_alertings["by_rule_id"][rule_id][node_id]){
                    var this_alerting_service_data = available_setup_alertings["by_rule_id"][rule_id][node_id][alerting_service_id];

                    if(this_alerting_service_data["warn_alert_after"] == 0){
                        warn_alert_after_text = "Immediately after WARN detected"
                        warn_alert_after_bg_color = "bg-success";
                    }else if(this_alerting_service_data["warn_alert_after"] > 0){
                        warn_alert_after_text = this_alerting_service_data["warn_alert_after"] + " minute(s) after WARN detected"
                        warn_alert_after_bg_color = "bg-success";
                    }

                    if(this_alerting_service_data["crit_alert_after"] == 0){
                        crit_alert_after_text = "Immediately after CRIT detected"
                        crit_alert_after_bg_color = "bg-success";
                    }else if(this_alerting_service_data["crit_alert_after"] > 0){
                        crit_alert_after_text = this_alerting_service_data["crit_alert_after"] + " minute(s) after CRIT detected"
                        crit_alert_after_bg_color = "bg-success";
                    }

                    if(this_alerting_service_data["alerting_user_ids"].length > 0){
                        $.each(this_alerting_service_data["alerting_user_ids"], function(arrkey, userID){
                            target_service.find(".alerting-info-alerts-to-recepients").append("<li>" + users[userID]["username"] + " - " + users[userID]["name"]  + " " + users[userID]["lastname"] + "</li>");
                        });
                    }else{
                        target_service.find(".alerting-info-alerts-name").text("Nobody");
                    }
                }else{
                    target_service.find(".alerting-info-alerts-name").text("Nobody");
                }

                target_service.find(".alerting-info-alerts-warn").text(warn_alert_after_text).removeClass("bg-danger").removeClass("bg-success").addClass(warn_alert_after_bg_color);
                target_service.find(".alerting-info-alerts-crit").text(crit_alert_after_text).removeClass("bg-danger").removeClass("bg-success").addClass(crit_alert_after_bg_color);    
            });
            $("#alerting-services-info-container").show();
            $("#alerting-services-info-none-configured").hide();
        }else{
            $("#alerting-services-info-container").hide();
            $("#alerting-services-info-none-configured").show();   
        }

        $("#configured_rule_alerting_help_modal").modal("show");
    });
}

function initAlertingTypesDropdown(){
    $(".alerting-types-dropdown .dropdown-item").off("click");
    $(".alerting-types-dropdown .dropdown-item").on("click", function(){
        var nodeid = $(this).attr("data-node-id");
        var typeid = $(this).attr("data-type-id");
        var perc_or_min = available_custom_rules[typeid]["perc_or_min"];
        var this_configurable_services = $("#configurable_services_" + nodeid);
    
        custom_rules_tosave[nodeid] = {
            "service_type" : typeid
        };
    
        this_configurable_services.find(".input-group-prepend .warn_level").text((perc_or_min == 0 ? "Warn at" : "Warn after"));
        this_configurable_services.find(".input-group-append .warn_perc_min").text((perc_or_min == 0 ? "% usage" : "minute(s)"));
        this_configurable_services.find(".input-group-append .crit_level").text((perc_or_min == 0 ? "Warn at" : "Warn after"));
        this_configurable_services.find(".input-group-append .crit_perc_min").text((perc_or_min == 0 ? "% usage" : "minute(s)"));
        this_configurable_services.find(".add-rules-input.warn-input").removeAttr("disabled").val("");
        this_configurable_services.find(".add-rules-input.crit-input").removeAttr("disabled").val("");
        $(".alerting-types-button[data-node-id='" + nodeid + "']").text(available_custom_rules[typeid]["service_desc"]);
    
        var target_service_select = $(".alerting-services-dropdown[data-node-id='" + nodeid + "'");
        target_service_select.children().remove();
        $.each(available_custom_rules[typeid]["available_services"][nodeid]["configurable_services"], function(arrkey, service_desc){
            target_service_select.append("<a class='dropdown-item' data-sort-id=" + arrkey + " data-service-desc='" + service_desc + "' href='#'>" + service_desc + "</a>");
        });
        $(".alerting-services-button[data-node-id='" + nodeid + "']").removeAttr("disabled");
        $(".alerting-services-button[data-node-id='" + nodeid + "'").text("Select service");
    
        $(".alerting-services-dropdown .dropdown-item").off("click");
        $(".alerting-services-dropdown .dropdown-item").on("click", function(){
            var selected_service = available_custom_rules[typeid]["available_services"][nodeid]["configurable_services"][$(this).attr("data-sort-id")];
            $(".alerting-services-button[data-node-id='" + nodeid + "'").text(selected_service);
            custom_rules_tosave[nodeid]["service_name"] = selected_service;
            checkAddRuleDataComplete(nodeid);
        });
    
        $(".add-rules-input.warn-input").off("change");
        $(".add-rules-input.warn-input").on("change", function(){
            custom_rules_tosave[nodeid]["warn_at_after"] = $(this).val();
            checkAddRuleDataComplete(nodeid);
        });
    
        $(".add-rules-input.warn-input").off("input");
        $(".add-rules-input.warn-input").on("input", function(){
            custom_rules_tosave[nodeid]["warn_at_after"] = $(this).val();
            checkAddRuleDataComplete(nodeid);
        });
    
        $(".add-rules-input.crit-input").off("input");
        $(".add-rules-input.crit-input").on("input", function(){
            custom_rules_tosave[nodeid]["crit_at_after"] = $(this).val();
            checkAddRuleDataComplete(nodeid);
        });
    
        $(".add-custom-rule").off("click");
        $(".add-custom-rule").on("click", function(){
            if(checkAddRuleDataComplete(nodeid)){
                custom_rules_tosave[nodeid]["nodeid"] = nodeid;
                custom_rules_tosave[nodeid]["monitor"] = 1;
    
                window.sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "addCustomRule", custom_rules_tosave[nodeid]);
            }else{
                showMessage(1, "Cannot save, some data missing.");
            }
        });
    });
}


function checkAddRuleDataComplete(nodeid){
    var target_custom_rule = custom_rules_tosave[nodeid];
    var all_fields_stated = true;

    if(!("service_type" in target_custom_rule) || target_custom_rule["service_type"].trim() < 1) all_fields_stated = false;
    if(!("service_name" in target_custom_rule) ||  target_custom_rule["service_name"][0].trim() == "") all_fields_stated = false;
    if(!("warn_at_after" in target_custom_rule) || target_custom_rule["warn_at_after"].trim() < 0) all_fields_stated = false;
    if(!("crit_at_after" in target_custom_rule) ||  target_custom_rule["crit_at_after"].trim() < 0) all_fields_stated = false;

    var target_save_button = $(".add-custom-rule[data-node-id='" + nodeid + "'");
    if(all_fields_stated) target_save_button.show();
    else target_save_button.hide();

    return all_fields_stated;
}

function initWarnAndCritInputs(){
    $(".edit-rules-input.warn-input").off("click");
    $(".edit-rules-input.crit-input").off("click");

    $(".edit-rules-input.warn-input").on("change", function(){
        var rule_id = $(this).closest(".rule").attr("data-rule-id");
        var rule_value = $(this).val();
        var rule_warn_crit = "warn_at_after";
        detectRuleChange(rule_id, rule_value, rule_warn_crit);
    });

    $(".edit-rules-input.crit-input").on("change", function(){
        var rule_id = $(this).closest(".rule").attr("data-rule-id");
        var rule_value = $(this).val();
        var rule_warn_crit = "crit_at_after";
        detectRuleChange(rule_id, rule_value, rule_warn_crit);
    });
}

function detectRuleChange(rule_id, rule_value, rule_warn_crit){
    if(alerting_rules["by_rule_id"][rule_id][rule_warn_crit] != rule_value && $.isNumeric(rule_value)){
        $("#save-" + rule_id).show();
        $("#restore-" + rule_id).show();
        return true;
    }
    $("#save-" + rule_id).hide();
    $("#restore-" + rule_id).hide();
    return false;
}

function initRestorePreviousDefault(){
    $(".restore-rule").off("click");
    $(".restore-rule").on("click", function(){
        var rule_id = $(this).closest(".rule").attr("data-rule-id");
        var warn_at_after = alerting_rules["by_rule_id"][rule_id]["warn_at_after"];
        var crit_at_after = alerting_rules["by_rule_id"][rule_id]["crit_at_after"];
        $("#warn_at_after_" + rule_id).val(warn_at_after);
        $("#crit_at_after_" + rule_id).val(crit_at_after);
        $("#restore-" + rule_id).hide();
        $("#save-" + rule_id).hide();
    });
}

function initSaveRuleChange(){
    $(".save-rule").off("click");
    $(".save-rule").on("click", function(){
        var rule_id = $(this).closest(".rule").attr("data-rule-id");
        var data = {
            "rule_id" : rule_id,
            "warn_level" : $("#warn_at_after_" + rule_id).val(),
            "crit_level" : $("#crit_at_after_" + rule_id).val()
        }

        window.sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "editConfiguredRule", data);
    });
}

function initRemoveRule(){
    $(".remove-rule").off("click");
    $(".remove-rule").on("click", function(){
        var rule_id = $(this).closest(".rule").attr("data-rule-id");
        var target_rule = alerting_rules["by_rule_id"][rule_id];

        $("#remove_rule_id").text(rule_id);
        $("#remove_rule_node").text(target_rule["hostname"]);
        $("#remove_rule_service_desc").text(target_rule["service_desc"]);
        $("#remove_rule_target").text(target_rule["rule_target"]);
        $("#remove_rule_modal_button").prop("disabled", true);
        $("#permanently_remove_timer").text(5);

        clearTimeout(intervals["remove_service"]);
        intervals["remove_service"] = setInterval(function () {
            var timernow = $("#permanently_remove_timer").text();
            if((timernow-1) > 0){
              $("#permanently_remove_timer").text((timernow-1));
            }else{
              $("#permanently_remove_timer").text(0);
              $("#remove_rule_modal_button").removeAttr("disabled");
              clearTimeout(intervals["remove_service"]);
            }
        }, 1000);

        $("#remove_rule_modal").modal("show");

        $("#remvoe_rule_modal_cancel").off("click");
        $("#remvoe_rule_modal_cancel").on("click", function(){
            clearTimeout(intervals["remove_service"]); 
        });

        $("#remove_rule_modal_button").off("click");
        $("#remove_rule_modal_button").on("click", function(){
            data = {
                "rule_id" : rule_id
            }

            window.sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "removeConfiguredCustomRule", data);
        });
    });
}

function addCustomRule(ruledata){
    $("#custom_rules_" + ruledata["node_id"]).append(
        "<div class='input-group mb-3 custom-rule rule' data-node-id='" + ruledata["node_id"] + "' data-rule-id='" + ruledata["id"] + "'>" +
            "<div class='input-group-prepend'>" +
                "<span class='input-group-text service-description'>" + ruledata["service_desc"] + "</span>" +
                "<span class='input-group-text target-service'>" + ruledata["rule_target"] + "</span>" +
                "<span class='input-group-text bg-warning warn_level'>" + (ruledata["perc_or_min_value"] == 0 ? "Warn at" : "Warn after" ) + "</span>" +
            "</div>" +
            "<input id='warn_at_after_" + ruledata["id"] + "' type='number' min='0' class='form-control edit-rules-input warn-input' value='" + ruledata["warn_at_after"] + "'>" +
            "<div class='input-group-append'>" +
                "<span class='input-group-text bg-warning warn_perc_min'>" + (ruledata["perc_or_min_value"] == 0 ? "% usage" : "minutes" ) + "</span>" +
            "</div>" +
            "<div class='input-group-append'>" +
                "<span class='input-group-text bg-danger crit_level'>" + (ruledata["perc_or_min_value"] == 0 ? "Crit at" : "Crit after" ) + "</span>" +
            "</div>" +
            "<input id='crit_at_after_" + ruledata["id"] + "' type='number' min='0' class='form-control edit-rules-input crit-input' value='" + ruledata["crit_at_after"] + "'>" +
            "<div class='input-group-append'>" +
                "<span class='input-group-text bg-danger crit_perc_min'>" + (ruledata["perc_or_min_value"] == 0 ? "% usage" : "minutes" ) + "</span>" +
            "</div>" +
            "<div class='input-group-append'>" +
                "<button id='help-" + ruledata["id"] + "' class='btn btn-info help-rule fa-solid fa-circle-question' type='button'></button>" +
                "<button id='remove-" + ruledata["id"] + "' class='btn btn-danger remove-rule wsbutton' type='button'><i class='fa-solid fa-minus'></i></button>" +
                "<button id='restore-" + ruledata["id"] + "' class='btn btn-warning restore-rule wsbutton' type='button' style='display: none;'><i class='fa-solid fa-rotate-left'></i></button>" +
                "<button id='save-" + ruledata["id"] + "' class='btn btn-success fa-solid fa-floppy-disk save-rule wsbutton' type='button' style='display: none;'></button>" +
            "</div>" +
        "</div>"
    );
    alerting_rules["by_rule_id"][ruledata["id"]] = ruledata;
}

function adaptServiceTypeWidth(){
    setTimeout(function(){
        $.each(nodes, function(arrkey, node){
            var maxWidth = Math.max.apply(null, $("#custom_rules_" + node["nodeid"] + " .target-service:visible").map(function (){
                return $(this).outerWidth();
            }).get());
    
            $("#custom_rules_" + node["nodeid"] + " .target-service:visible").css("width", maxWidth);

            var maxWidth = Math.max.apply(null, $("#alerting_custom_rules_" + node["nodeid"] + " .target-service:visible").map(function (){
                return $(this).outerWidth();
            }).get());

            $("#alerting_custom_rules_" + node["nodeid"] + " .target-service:visible").css("width", maxWidth);
        });
    }, 500);
}

function resetCreateCustomRule(nodeid){
    var target_create_rule = $("#configurable_services_" + nodeid);
    target_create_rule.find(".alerting-types-button").text("Select type");
    target_create_rule.find(".alerting-services-button").text("Select service");
    target_create_rule.find(".warn_level").text("");
    target_create_rule.find(".warn_perc_min").text("");
    target_create_rule.find(".crit_level").text("");
    target_create_rule.find(".crit_perc_min").text("");
    target_create_rule.find(".warn-input").val("");
    target_create_rule.find(".crit-input").val("");
    target_create_rule.find(".add-custom-rule").hide();
    if(nodeid in custom_rules_tosave) delete custom_rules_tosave[nodeid];
}

function recreateTypeDropdown(nodeid){
    var target_dropdown = $(".alerting-types-dropdown[data-node-id='" + nodeid + "'");
    target_dropdown.children().remove();
    $.each(available_custom_rules, function(typeid, typedata){
        if((nodeid in typedata["available_services"]) && ("configurable_services" in typedata["available_services"][nodeid]) && typedata["available_services"][nodeid]["configurable_services"].length > 0){
            target_dropdown.append("<a class='dropdown-item' data-node-id=" + nodeid + " data-type-id=" + typeid + " href='#'>" + typedata["service_desc"] + "</a>") 
        }
    });
}

function initEditAlertingRule(){
    $(".edit-alerting-rule").off("click");
    $(".edit-alerting-rule").on("click", function(){
        var rule_id = $(this).attr("data-rule-id");
        var node_id = $(this).attr("data-node-id");
        var target_rule = alerting_rules["by_rule_id"][rule_id];

        alerting_rule_tosave = {
            "node_id" : node_id,
            "rule_id" : rule_id,
            "alerting" : {},
            "users" : {}
        };
        
        if("by_rule_id" in available_setup_alertings && rule_id in available_setup_alertings["by_rule_id"]){
            $.each(available_setup_alertings["by_rule_id"][rule_id][node_id], function(alerting_service_id, alerting_service_data){
                if(!(alerting_service_id in alerting_rule_tosave["alerting"])) alerting_rule_tosave["alerting"][alerting_service_id] = {};
                if(!(alerting_service_id in alerting_rule_tosave["users"])) alerting_rule_tosave["users"][alerting_service_id] = {};
                alerting_rule_tosave["alerting"][alerting_service_id]["warn_exceeds"] = alerting_service_data["warn_alert_after"];
                alerting_rule_tosave["alerting"][alerting_service_id]["crit_exceeds"] = alerting_service_data["crit_alert_after"];
                alerting_rule_tosave["users"][alerting_service_id] = alerting_service_data["alerting_user_ids"];
            });
        }

        $("#configure_rule_alerting_rule_id").text(rule_id);
        $("#configure_rule_alerting_system_type").text((target_rule["rule_default"] == 1 ? "Default" : "Custom (Overwrites default rule for this node)"));
        $("#configure_rule_alerting_target").text((target_rule["system_target"] == 1 ? "All nodes (default rule)" : target_rule["hostname"]));
        $("#configure_rule_alerting_service_type").text(target_rule["service_desc"]);
        $("#configure_rule_alerting_service_target").text((target_rule["rule_target"] === null ? "None" : target_rule["rule_target"]));
        $("#configure_rule_alerting_warn_level").text(target_rule["perc_or_min_value"] == 0 ? "When " + target_rule["warn_at_after"] + "% usage reached." : "After a total downtime of " + target_rule["warn_at_after"] + " minutes.");
        $("#configure_rule_alerting_crit_level").text(target_rule["perc_or_min_value"] == 0 ? "When " + target_rule["crit_at_after"] + "% usage reached." : "After a total downtime of " + target_rule["crit_at_after"] + " minutes.");

        $("#edit-rule-alerting-services-tabs .nav-link").first().addClass("active");
        $("#edit-rule-alerting-services-pane .tab-pane").first().addClass("show active");

        $("#save-rule-alerting").off("click");
        $(".edit-rule-alerting-warn-immediately").off("click");
        $(".edit-rule-alerting-warn-custom").off("click");
        $(".edit-rule-alerting-crit-immediately").off("click");
        $(".edit-rule-alerting-crit-custom").off("click");
        $("#edit-rule-alerting-services-tabs .nav-link").removeClass("active").first().addClass("active");
        $("#edit-rule-alerting-services-pane .tab-pane").removeClass("active").first().addClass("active");

        if(target_rule["perc_or_min"] == 1){
            $(".edit-rule-alerting-warn-custom").val("").parent().hide();
            $(".edit-rule-alerting-crit-custom").val("").parent().hide();
        }else{             
            $(".edit-rule-alerting-warn-custom").val("").on("input", function(){
                var service_id = $(this).attr("data-service-id");
                if(!(service_id in alerting_rule_tosave["alerting"])) alerting_rule_tosave["alerting"][service_id] = {};
                alerting_rule_tosave["alerting"][service_id]["warn_exceeds"] = parseInt($(this).val());
            }).parent().show();
              
            $(".edit-rule-alerting-crit-custom").val("").on("input", function(){
                var service_id = $(this).attr("data-service-id");
                if(!(service_id in alerting_rule_tosave["alerting"])) alerting_rule_tosave["alerting"][service_id] = {};
                alerting_rule_tosave["alerting"][service_id]["crit_exceeds"] = parseInt($(this).val());
            }).parent().show();    
        }

        $(".edit-rule-alerting-warn-immediately").prop("checked", false).on("click", function(){
            var service_id = $(this).attr("data-service-id");
            if(!(service_id in alerting_rule_tosave["alerting"])) alerting_rule_tosave["alerting"][service_id] = {};
            if($(this).is(':checked')){
                $(".edit-rule-alerting-warn-custom").parent().hide();
                alerting_rule_tosave["alerting"][service_id]["warn_exceeds"] = 0;
            }else{
                if(target_rule["perc_or_min"] == 0) $(".edit-rule-alerting-warn-custom[data-service-id='" + service_id + "']").parent().show();
                alerting_rule_tosave["alerting"][service_id]["warn_exceeds"] = -1;
            }
        });

        $(".edit-rule-alerting-crit-immediately").prop("checked", false).on("click", function(){
            var service_id = $(this).attr("data-service-id");
            if(!(service_id in alerting_rule_tosave["alerting"])) alerting_rule_tosave["alerting"][service_id] = {};
            if($(this).is(':checked')){
                $(".edit-rule-alerting-crit-custom").parent().hide();
                alerting_rule_tosave["alerting"][service_id]["crit_exceeds"] = 0;
            }else{
                if(target_rule["perc_or_min"] == 0) $(".edit-rule-alerting-crit-custom[data-service-id='" + service_id + "']").parent().show();
                alerting_rule_tosave["alerting"][service_id]["crit_exceeds"] = -1;
            }
        });

        $(".edit-rule-alerting-contacts").multiselect({
            includeSelectAllOption: true,
            disableIfEmpty: true,
            buttonWidth: '100%',
            numberDisplayed: 5,
            onSelectAll: function() {
                alertingUsersSelectChanged();
            },
            onChange: function() {
                alertingUsersSelectChanged();
              },
            onDeselectAll: function() {
                alertingUsersSelectChanged();
            }
        });
        $(".edit-rule-alerting-contacts").multiselect('deselectAll', false);

        $("#save-rule-alerting").on("click", function(){
            if("alerting" in alerting_rule_tosave && Object.keys(alerting_rule_tosave["alerting"]).length > 0)
                window.sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "editAddAlertingRule", alerting_rule_tosave);
            else
                $("#configure_rule_alerting_modal").modal("hide");  
        });
        

        if("by_rule_id" in available_setup_alertings && rule_id in available_setup_alertings["by_rule_id"]){
            $.each(available_setup_alertings["by_rule_id"][rule_id][node_id], function(service_id, service_data){
                if(service_data["warn_alert_after"] == 0){
                    $(".edit-rule-alerting-warn-immediately[data-service-id='" + service_data["alerting_service"] + "']").prop("checked", true);
                    $(".edit-rule-alerting-warn-custom[data-service-id='" + service_data["alerting_service"] + "']").parent().hide();
                }else if(service_data["warn_alert_after"] > 0){
                    $(".edit-rule-alerting-warn-custom[data-service-id='" + service_data["alerting_service"] + "']").val(service_data["warn_alert_after"]).parent().show();
                }

                if(service_data["crit_alert_after"] == 0){
                    $(".edit-rule-alerting-crit-immediately[data-service-id='" + service_data["alerting_service"] + "']").prop("checked", true);
                    $(".edit-rule-alerting-crit-custom[data-service-id='" + service_data["alerting_service"] + "']").parent().hide();
                }else if(service_data["crit_alert_after"] > 0){
                    $(".edit-rule-alerting-crit-custom[data-service-id='" + service_data["alerting_service"] + "']").val(service_data["crit_alert_after"]).parent().show();
                }

                var target_select = $(".edit-rule-alerting-contacts[data-service-id='" + service_id + "']");
                $.each(service_data["alerting_user_ids"], function(arrkey, alerting_user_id){
                    target_select.multiselect("select", alerting_user_id);
                });
            });
        }

        $("#configure_rule_alerting_modal").modal("show");
   });
}

function alertingUsersSelectChanged(element){
    var service_id = $(".edit-rule-alerting-contacts:visible").attr("data-service-id");
    alerting_rule_tosave["users"][service_id] = [];
    $.each($(".edit-rule-alerting-contacts[data-service-id='" + service_id + "'] option:selected"), function(){
        alerting_rule_tosave["users"][service_id].push($(this).val());
    });
}

function reloadSetupAlerting(){
    $.get(frontend + "/sites/alerting/templates/setup_alerting_card.php", {}, function(response) {
        $("#setup-alerting").html(response);
        resetSetupAlertingNodeTabs();
        adaptServiceTypeWidth();
        initEditAlertingRule();
        initHelpAlertingRule();
    });
}

function messagesTrigger(data){
    var key = Object.keys(data);
 
    if(data[key]["status"] == 0){
        if(key == "editConfiguredRule"){
            var rule_id = data[key]["data"]["rule_id_changed"];
            alerting_rules = data[key]["data"]["saved_values"];
            $("#save-" + rule_id).hide();
            $("#restore-" + rule_id).hide();
            reloadSetupAlerting();
        }else if(key == "addCustomRule"){
            var returned_custom_rules = data[key]["data"];
            if(("by_rule_id" in returned_custom_rules)){
                $.each(returned_custom_rules["by_rule_id"], function(ruleid, ruledata){
                    var nodeid = ruledata["node_id"];
                    if(nodeid > 1){
                        addCustomRule(returned_custom_rules["by_rule_id"][ruleid]);
                        resetCreateCustomRule(nodeid);
                        adaptServiceTypeWidth();
                        $("#services-dropdown-" + nodeid).prop("disabled", true);
                        $("#configurable_services_" + nodeid + " .alerting-services-dropdown").children().remove();
                        window.sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "getAvailableRuleTypesAndServices", {"nodeid" : nodeid});
                    }
                });
            }
        }else if(key == "getAvailableRuleTypesAndServices"){
            $.each(data[key]["data"], function(service_id, service_details){
                $.each(service_details["available_services"], function(node_id, node_avail_services){
                    if(node_avail_services.length == 0){
                        $("#configurable_services_" + node_id).find(".dropdown-item[data-type-id='" + service_id + "'").remove();
                    }else if(node_avail_services.length > 0){
                        $("#configurable_services_" + node_id + " .dropdown-item").sort(function (a, b) {
                            return parseInt(a.attr("data-type-id")) > parseInt(b.attr("data-type-id"));
                        }).each(function () {
                            var elem = $(this);
                            elem.remove();
                            $(elem).appendTo("#configurable_services_" + node_id + " .dropdown-item");
                        });
                    }
                    available_custom_rules[service_id]["available_services"][node_id] = node_avail_services;
                    recreateTypeDropdown(node_id);
                });
            });
            reloadSetupAlerting();
            initWarnAndCritInputs();
            initRestorePreviousDefault();
            initSaveRuleChange();
            initRemoveRule();
            initAlertingTypesDropdown();
            initHelpRule();
            initEditAlertingRule();
        }else if(key == "removeConfiguredCustomRule"){
            var nodeid = data[key]["data"]["node_id"];
            var rule_id = data[key]["data"]["rule_id"];
            $(".rule[data-rule-id='" + rule_id + "']").remove();
            $("#remove_rule_modal").modal("hide");
            window.sendToWSS("backendRequest", "ChiaMgmt\\Alerting\\Alerting_Api", "Alerting_Api", "getAvailableRuleTypesAndServices", {"nodeid" : nodeid});
        }else if(key == "editAddAlertingRule"){
            var rule_id = data[key]["data"]["rule_id"];
            var node_id = data[key]["data"]["node_id"];

            if("by_rule_id" in data[key]["data"]["new_data"]){
                var rule_data = data[key]["data"]["new_data"]["by_rule_id"][rule_id];
                $.each(rule_data, function(nodeid, db_rule_data){
                    var class_removed = false;
                    $.each(db_rule_data, function(alerting_service_id, alerting_service_data){
                        var target_alerting_rule = $("#setup-alerting-node-content-" + nodeid + " .rule[data-rule-id='" + alerting_service_data["rule_id"] + "']");
                        if(!class_removed){
                            if("by_rule_id" in available_setup_alertings && alerting_service_data["rule_id"] in available_setup_alertings["by_rule_id"] && nodeid in available_setup_alertings["by_rule_id"][alerting_service_data["rule_id"]]){
                                delete available_setup_alertings["by_rule_id"][alerting_service_data["rule_id"]][nodeid];
                            }
                            target_alerting_rule.find(".alerting-service-check").removeClass("bg-success").addClass("bg-secondary");
                            class_removed = true;
                        }
                        if(alerting_service_data["warn_alert_after"] >= 0 || alerting_service_data["crit_alert_after"] >= 0){
                            target_alerting_rule.find(".alerting-service-check[data-alerting-service-id='" + alerting_service_id + "']").removeClass("bg-secondary").removeClass("bg-success").removeClass("bg-warning").addClass(alerting_service_data["alerting_user_ids"].length > 0 ? "bg-success" : "bg-warning");
                        }
    
                        if(!(alerting_service_data["rule_id"] in available_setup_alertings["by_rule_id"])) available_setup_alertings["by_rule_id"][alerting_service_data["rule_id"]] = {};
                        if(!(nodeid in available_setup_alertings["by_rule_id"][alerting_service_data["rule_id"]])) available_setup_alertings["by_rule_id"][alerting_service_data["rule_id"]][nodeid] = {};
                        if(!(alerting_service_id in available_setup_alertings["by_rule_id"][alerting_service_data["rule_id"]][nodeid])) available_setup_alertings["by_rule_id"][alerting_service_data["rule_id"]][nodeid][alerting_service_id] = {};
                        available_setup_alertings["by_rule_id"][alerting_service_data["rule_id"]][nodeid][alerting_service_id] = alerting_service_data;
                    });
                });
            }else{
                $("#setup-alerting-node-content-" + node_id + " .rule[data-rule-id='" + rule_id + "'] .alerting-service-check").removeClass("bg-success").removeClass("bg-warning").addClass("bg-secondary");
                delete available_setup_alertings["by_rule_id"][rule_id][node_id];
            }

            $("#configure_rule_alerting_modal").modal("hide");
        }

        showMessage(0, data[key]["message"]);
    }else{
        showMessage(1, data[key]["message"]);
    }
}