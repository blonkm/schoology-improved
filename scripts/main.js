(function () {
    // alternative to jQuery $ function
    function get(el) {
        return document.getElementById(el);
    }

    function showSelectedFile(path) {
        var backslash = path.lastIndexOf("\\");
        var filename = path.substr(backslash + 1);
        var message = document.getElementById('selectedFile');
        message.innerHTML = 'selected: ' + filename;
        message.className = 'filename';
    }

    function domReady(fn) {
        // If we're early to the party
        document.addEventListener("DOMContentLoaded", fn);
        // If late; I mean on time.
        if (document.readyState === "interactive" || document.readyState === "complete") {
            fn();
        }
    }

    function reloadFromAPI(event) {
        event.preventDefault();
        this.innerText = "busy...";
        document.body.style.cursor = 'wait';
        fetch(location.href + '&cache=no').then(function (response) {
            console.log('success!', response);
            location.reload();
        }).catch(function (err) {
            alert('failed to reload');
            this.innerText = "reload";
            console.warn('Something went wrong.', err);
            document.body.style.cursor = 'default';
        });
        return false;
    }

    function initSort() {
        Array.from(document.querySelectorAll('.sortedTable')).forEach(function (table) {
            var sort = new Tablesort(table);
        });
    }

    function initDropzone() {
        if (document.querySelector("#frmImport")) {
            new Dropzone("#frmImport", {
                maxFilesize: 2, // MB
                maxFiles: 1,
                init: function () {
                    this.on("success", function (file, responseText) {
                        var el = document.querySelector('#preview');
                        el.innerHTML = responseText;
                    });
                }
            });
        }
    }

    function getIdOfDatalist(value) {
        var item = document.querySelectorAll('datalist option[value="' + value + '"]');
        if (item.length == 0) {
            return;
        }
        var id = item[0].dataset.id;
        return id;
    }

    function selectCourse() {
        var selectedCourse = get("course").value;
        var id = getIdOfDatalist(selectedCourse);
        if (!id) {
            return;
        }
        window.location.href = "groups.php?section=" + id + "&action=matrix";
    }
    
    // safely add event handler to an element
    function addEvent(elementName, f, eventName) {
        var element = get(elementName);
        if (!!element) {
            element.addEventListener(eventName, f, false);
        }

    }

    // safely add a click event handler
    function addClickEvent(elementName, f) {
        addEvent(elementName, f, 'click');
    }
    
    // create the faux select box with typeahead (using awesomplete js)
    function createTypeAhead() {
        var input = document.querySelectorAll('input.dropdown-input');
        if (input.length === 0) {
            return;
        }
        var comboplete = new Awesomplete('input.dropdown-input', {
            minChars: 0,
            maxItems: 200,
            sort: (a,b) => a<b,
        });
        Awesomplete.$('.dropdown-btn').addEventListener("click", function () {
            if (comboplete.ul.childNodes.length === 0) {
                comboplete.minChars = 0;
                comboplete.evaluate();
            } else if (comboplete.ul.hasAttribute('hidden')) {
                comboplete.open();
            } else {
                comboplete.close();
            }
        });
    }

    // make sure enter key will hit GO button
    function triggerEnter() {
        if (event.keyCode === 13 && event.target.text !== '') {
            event.preventDefault();
            document.getElementById("selectCourseButton").click();
        }
    }

    domReady(function () {
        // init functionality
        initSort();
        initDropzone();
        createTypeAhead();
        // events
        addClickEvent("reloadButton", reloadFromAPI);
        addEvent('course', triggerEnter, 'keyup');
        addClickEvent("selectCourseButton", selectCourse);

    });

    Dropzone.autoDiscover = false;

})();
