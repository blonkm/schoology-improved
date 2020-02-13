(function () {
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
        Array.from(document.querySelectorAll('.sortedTable')).forEach(function(table) {
          var sort = new Tablesort(table);         
        });        
    }
    
    function initDropzone() {
        new Dropzone("#frmImport", { 
            maxFilesize: 2, // MB
            maxFiles: 1,
            init: function() {
                this.on("success", function(file, responseText) {
                    var el = document.querySelector('#preview');
                    el.innerHTML = responseText;
                });
            }
        });
    }
    
    domReady(function () {
        var reloadButton = document.getElementById("reloadButton");
        reloadButton.addEventListener('click', reloadFromAPI, false);
        initSort();
        initDropzone();
    });
    
Dropzone.autoDiscover = false;

})();