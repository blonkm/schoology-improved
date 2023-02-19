/**
 * [This object converts an HTML table to CSV format]
 * @type {Object}
 * @author Cristian Alanis <snake404@gmail.com>
 * @version 1.0.0
 * @since 1.0.0
 */
var tableToCSV = {

   convertToCSV: function (tableId, fileName) {
      var table = document.getElementById(tableId);
      var result = "data:text/csv;charset=utf-8;filename=" + fileName + ",";
      var sizeRows = table.children[0].childNodes[1].cells.length;

      for (var i = 0, row; row = table.rows[i]; i++) {
         var tmp = '';
         for (var j = 0, col; col = row.cells[j]; j++) {
            if (j < sizeRows - 1) {
               tmp += col.innerText + ', ';
            } else {
               tmp += col.innerText + "\r\n";
            }

         }
         result += tmp;
      }

      var encodedUri = encodeURI(result);
      var link = document.createElement("a");
      link.href = encodedUri;

      link.style = "visibility:hidden";
      link.download = fileName;

      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
   }

}
