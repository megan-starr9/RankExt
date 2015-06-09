$(document).on('click','#create_tier', function() {
  console.log("HERE");
  $.ajax({
    url: "../xmlhttp.php",
    data: {
      action : 'rankext_addtier'
    },
    type: "post",
    dataType: 'html',
    success: function(response){
      location.reload();
    },
    error: function(response) {
      alert("There was an error "+response.responseText);
    }
  });
});

$(document).on('click', '#create_rank', function() {
  $.ajax({
    url: "../xmlhttp.php",
    data: {
      action : 'rankext_addrank'
    },
    type: "post",
    dataType: 'html',
    success: function(response){
      location.reload();
    },
    error: function(response) {
      alert("There was an error "+response.responseText);
    }
  });
});
