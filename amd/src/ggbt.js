/**
 * Javascript Controller to embed GGBApplet.
 *
 * TEACHER/EDITING VIEW
 *
 * @module     qtype_geogebra/ggbt
 * @author     Christoph Stadlbauer <christoph.stadlbauer@geogebra.org>
 * @copyright  (c) International GeoGebra Institute 2018
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'https://www.geogebra.org/apps/deployggb.js'], function($, GGBApplet) {

    return {

        init: function() {
            window.GGBT = this;
            window.ggbAppletOnLoad = function() {
                $('input[name="ggbparameters"]').val(JSON.stringify(window.applet1.getParameters()));
                $('input[name="ggbviews"]').val(JSON.stringify(window.applet1.getViews()));
                $('input[name="ggbcodebaseversion"]').val(window.applet1.getHTML5CodebaseVersion());

                if (typeof (this.ggbcheckb) == "undefined") {
                    var applet = document.ggbApplet;
                    $('input[name="ggbxml"]').val(applet.getXML());

                    var randomizedvar = document.getElementById('id_randomizedvar');
                    if (!randomizedvar.value) {
                        window.GGBT.getrandvars();
                    }

                    var i = 0;
                    var answer = $('#id_answer_' + i);
                    while (answer[0] !== undefined) {
                        if (answer.val()) {
                            answer.on('change focus', function(e) {
                                e.preventDefault();
                                window.GGBT.updateFeedback($(e.target));
                            });
                            window.GGBT.updateFeedback(answer);
                        }
                        answer = $('#id_answer_' + ++i);
                    }
                    document.querySelector('article').onkeypress = window.GGBT.checkEnter;
                }
                if (window.GGBT.usefile.checked) {
                    document.getElementById('applet_container1').style.display = "block";
                    document.getElementById('applet_options').style.display = "flex";
                }
            };

            if ($('#applet_parameters')[0] !== undefined) {
                this.ggbDataset = $('#applet_parameters')[0].dataset;
                this.parameters = JSON.parse(this.ggbDataset.parameters);
                this.views = this.ggbDataset.views;
                window.applet1 = new GGBApplet(this.parameters, this.views, true);
                this.lang = this.ggbDataset.lang;
            }

            $('#id_loadapplet').on('click', function(e) {
                e.preventDefault();
                var id = $('#id_ggbturl').val().split("/").pop();
                if (id.indexOf("m") == 0) {
                    if (window.GGBT.isNumber(id.substr(1)) || (!window.GGBT.isNumber(id.substr(1)) && id.length > 8)) {
                        id = id.substr(1);
                    }
                }
                window.GGBT.injectapplet(id);
            });

            $('#id_getvars').on('click', function(e) {
                e.preventDefault();
                window.GGBT.getrandvars();
            });

            if (this.parameters) {
                window.applet1.inject("applet_container1", "preferHTML5");
            }

            this.ggbf = document.getElementById('id_ggbtheader');
            this.usefile = document.getElementById("id_usefile");

            if (this.ggbf === null) {
                // In this case we are editing a submission.
                this.ggbf = document.getElementById('id_submissiontypes');
                this.ggbcheckb = document.getElementById('id_assignsubmission_geogebra_enabled');
                if (this.ggbcheckb !== null) {
                    this.ggbcheckb.addEventListener('change', this.handleggbdisable, false);
                }
            }

            if (this.ggbf !== null) {
                this.ggbf.addEventListener('dragenter', this.handleDragEnter, false);
                this.ggbf.addEventListener('dragover', this.handleDragOver, false);
                this.ggbf.addEventListener('dragleave', this.handleDragEndLeave, false);
                this.ggbf.addEventListener('dragend', this.handleDragEndLeave, false);
                this.ggbf.addEventListener('drop', this.handleDrop, false);
                this.usefile.addEventListener('change', this.handleusefile, false);
            }

            if (this.usefile.checked) {
                document.getElementById('applet_options').style.display = "block";
            } else {
                document.getElementById('applet_options').style.display = "none";
            }
            this.initoptions();
        },

        /**
         * Callback for filepicker.
         *
         * @param {object} params The filepicker callback params.
         */
        callback: function(params) {
            var elementname = M.core_filepicker.instances[params.client_id].options.elementname;
            $('#id_' + elementname).val(params.url);
            // Inject applet to div layer.
            var id = (params.file).split(".")[0];
            if (id.indexOf("m") == 0) {
                if (this.isNumber(id.substr(1)) || (!this.isNumber(id.substr(1)) && id.length > 8)) {
                    id = id.substr(1);
                }
            }
            this.injectapplet(id);
        },

        /**
         * Inject the GeoGebra applet into the editing form.
         *
         * @param {string} id The material ID.
         */
        injectapplet: function(id) {
            this.parameters = {"material_id": id};
            this.parameters.language = this.lang;
            this.parameters.moodle = "editingQuestionOrSubmission";
            // Since we only support HTML5 this should work for js-code in the applet to get executed.
            this.parameters.useBrowserForJS = false;

            document.getElementById('applet_container1').style.display = "block";

            window.applet1 = new GGBApplet(this.parameters, true);
            window.applet1.inject("applet_container1", "preferHTML5");
        },

        /**
         * Get randomizable variables from the applet.
         */
        getrandvars: function() {
            var applet = document.ggbApplet;
            if (typeof applet === 'undefined') {
                return;
            }
            var objNumber = applet.getObjectNumber();
            var randomizedvar = document.getElementById('id_randomizedvar');
            var stringforrandomizedvars = "";
            var i = 0;
            for (var j = 0; j < objNumber; j++) {
                var strName = applet.getObjectName(j);
                if (applet.getObjectType(strName) == "numeric" && applet.isIndependent(strName)) {
                    stringforrandomizedvars += strName + ",";
                } else if (applet.getObjectType(strName) == "boolean") {
                    var answer = $('#id_answer_' + i);
                    if (answer !== null && answer.length > 0) {
                        if (!answer.val()) {
                            answer.val(strName);
                        }
                        this.updateFeedback(answer);
                        i++;
                    }
                }
                randomizedvar.value = stringforrandomizedvars;
            }
        },

        /**
         * Update feedback field from the GeoGebra variable caption.
         *
         * @param {jQuery} answernode The answer input jQuery element.
         */
        updateFeedback: function(answernode) {
            var id = answernode.attr('id').split("_").pop();
            var varname = answernode.val();
            if (!varname) {
                // Should not happen, but make sure this function does not fail.
                return;
            }
            var feedback = $('input[name="feedback[' + id + ']"]');
            var feedbackfromfile = $('#id_feedbackfromfile_' + id);
            const parser = new DOMParser();
            const xml = window.ggbApplet.getXML(varname);
            if (!xml) {
                // Should not happen, but make sure this function does not fail.
                return;
            }
            const doc = parser.parseFromString(xml, "text/xml");
            if (doc) {
                var elem = doc.getElementsByTagName('caption');
                var fbstring = '';
                if (elem.length == 1) {
                    fbstring = elem[0].getAttribute('val');
                    feedback.val(fbstring);
                } else if (elem.length > 1) {
                    feedback.val('');
                    fbstring = '';
                } else {
                    feedback.val('');
                    // This is rather an error condition but should be checked by the server.
                    fbstring = 'Caption not set or variable name wrong.';
                }
                feedbackfromfile.val(fbstring);
            }
        },

        /**
         * Prevent enter key from submitting the form.
         *
         * @param {Event} e The keyboard event.
         * @returns {boolean} True if the key should be allowed.
         */
        checkEnter: function(e) {
            e = e || event;
            var txtArea = /textarea/i.test((e.target || e.srcElement).tagName);
            return txtArea || (e.keyCode || e.which || e.charCode || 0) !== 13;
        },

        /**
         * Initialize applet option checkboxes.
         */
        initoptions: function() {
            this.enableRightClick = document.getElementById('enableRightClick');
            this.enableLabelDrags = document.getElementById('enableLabelDrags');
            this.showResetIcon = document.getElementById('showResetIcon');
            this.enableShiftDragZoom = document.getElementById('enableShiftDragZoom');
            this.showAlgebraInput = document.getElementById('showAlgebraInput');
            this.showMenuBar = document.getElementById('showMenuBar');
            this.showToolBar = document.getElementById('showToolBar');

            if (typeof this.parameters !== 'undefined') {
                this.enableRightClick.checked = this.parameters.enableRightClick;
                this.enableLabelDrags.checked = this.parameters.enableLabelDrags;
                this.showResetIcon.checked = this.parameters.showResetIcon;
                this.enableShiftDragZoom.checked = this.parameters.enableShiftDragZoom;
                this.showAlgebraInput.checked = this.parameters.showAlgebraInput;
                this.showMenuBar.checked = this.parameters.showMenuBar;
                this.showToolBar.checked = this.parameters.showToolBar;
            }

            this.enableRightClick.addEventListener('change', this.handlesettingschanged, false);
            this.enableLabelDrags.addEventListener('change', this.handlesettingschanged, false);
            this.showResetIcon.addEventListener('change', this.handlesettingschanged, false);
            this.enableShiftDragZoom.addEventListener('change', this.handlesettingschanged, false);
            this.showAlgebraInput.addEventListener('change', this.handlesettingschanged, false);
            this.showMenuBar.addEventListener('change', this.handlesettingschanged, false);
            this.showToolBar.addEventListener('change', this.handlesettingschanged, false);
        },

        /**
         * Handle applet settings checkbox changes.
         *
         * @param {Event} evt The change event.
         */
        handlesettingschanged: function(evt) {
            window.GGBT.parameters[evt.target.id] = (evt.target.checked);
            $('input[name="ggbparameters"]').val(JSON.stringify(window.GGBT.parameters));
            if (evt.target.id == "showToolBar" || evt.target.id == "showMenuBar"
                || evt.target.id == "showAlgebraInput") {
                window.applet1 = new GGBApplet(window.GGBT.parameters, true);
                window.applet1.inject("applet_container1", "preferHTML5");
            } else {
                window.ggbApplet[evt.target.id](evt.target.checked);
            }
        },

        /**
         * Handle the usefile checkbox toggle.
         */
        handleusefile: function() {
            if (!window.GGBT.usefile.checked) {
                document.getElementById('applet_container1').style.display = "none";
                document.getElementById('applet_options').style.display = "none";
            } else {
                document.getElementById('id_ggbturl').value = "";
            }
        },

        /**
         * Handle the GeoGebra submission checkbox disable.
         */
        handleggbdisable: function() {
            if (!window.GGBT.ggbcheckb.checked) {
                document.getElementById('applet_container1').style.display = "none";
                document.getElementById('applet_options').style.display = "none";
                if (window.GGBT.usefile.checked) {
                    window.GGBT.usefile.click();
                }
            } else {
                document.getElementById('applet_container1').style.display = "block";
            }
        },

        /**
         * Handle drag enter on the applet drop zone.
         */
        handleDragEnter: function() {
            if (typeof (window.GGBT.ggbcheckb) == "undefined" || window.GGBT.ggbcheckb.checked) {
                window.GGBT.ggbf.classList.add('qtype-geogebra-hover');
                document.getElementById('applet_container1').style.visibility = "hidden";
            }
        },

        /**
         * Handle drag over on the applet drop zone.
         *
         * @param {DragEvent} e The drag event.
         * @returns {boolean|undefined} False to prevent default.
         */
        handleDragOver: function(e) {
            if (typeof (window.GGBT.ggbcheckb) == "undefined" || window.GGBT.ggbcheckb.checked) {
                if (e.preventDefault) {
                    e.preventDefault();
                }
                window.GGBT.ggbf.classList.add('qtype-geogebra-hover');
                document.getElementById('applet_container1').style.visibility = "hidden";
                return false;
            }
            return undefined;
        },

        /**
         * Handle drag end/leave on the applet drop zone.
         */
        handleDragEndLeave: function() {
            if (typeof (window.GGBT.ggbcheckb) == "undefined" || window.GGBT.ggbcheckb.checked) {
                window.GGBT.ggbf.classList.remove('qtype-geogebra-hover');
                document.getElementById('applet_container1').style.removeProperty("visibility");
            }
        },

        /**
         * Handle file drop on the applet drop zone.
         *
         * @param {DragEvent} e The drop event.
         */
        handleDrop: function(e) {
            if (typeof (window.GGBT.ggbcheckb) == "undefined" || window.GGBT.ggbcheckb.checked) {
                e.preventDefault();
                e.stopPropagation();
                var file = e.dataTransfer.files[0];
                window.GGBT.ggbf.classList.remove('qtype-geogebra-hover');
                document.getElementById('applet_container1').style.removeProperty("visibility");
                document.getElementById('applet_container1').style.display = "block";
                document.getElementById('applet_options').style.display = "flex";
                document.getElementById('applet_container1').style.height = "100%";

                document.getElementById('id_ggbturl').value = "";
                if (!window.GGBT.usefile.checked) {
                    window.GGBT.usefile.click();
                }
                var reader = new FileReader();
                reader.onload = function(event) {
                    var base64 = event.target.result.replace("data:application/vnd.geogebra.file;base64,", "");
                    window.GGBT.parameters = {"ggbBase64": base64};
                    window.GGBT.parameters.enableRightClick = window.GGBT.enableRightClick.checked;
                    window.GGBT.parameters.enableLabelDrags = window.GGBT.enableLabelDrags.checked;
                    window.GGBT.parameters.showResetIcon = window.GGBT.showResetIcon.checked;
                    window.GGBT.parameters.enableShiftDragZoom = window.GGBT.enableShiftDragZoom.checked;
                    window.GGBT.parameters.showAlgebraInput = window.GGBT.showAlgebraInput.checked;
                    window.GGBT.parameters.showMenuBar = window.GGBT.showMenuBar.checked;
                    window.GGBT.parameters.showToolBar = window.GGBT.showToolBar.checked;
                    window.GGBT.parameters.moodle = "editingQuestionOrSubmission";
                    window.applet1 = new GGBApplet(window.GGBT.parameters, true);
                    window.applet1.inject("applet_container1");
                };

                reader.readAsDataURL(file);
            }
        },

        /**
         * Check if a value is a number.
         *
         * @param {*} n The value to check.
         * @returns {boolean} True if the value is numeric.
         */
        isNumber: function(n) {
            return !isNaN(parseFloat(n)) && isFinite(n);
        }
    };
});
