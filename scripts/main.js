 function showSelectedFile(path) {
    var backslash = path.lastIndexOf("\\");
    var filename = path.substr(backslash + 1);
    var message = document.getElementById('selectedFile');
    message.innerHTML = 'selected: ' + filename;
    message.className = 'filename';        
}
