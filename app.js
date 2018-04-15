var app = new Vue({
    el: '#app',
    data: {
        apiUrl: window.location.protocol+"//"+window.location.host+"/storage.php", // адрес API
        currentPage: 1,
        pagesCount: 1,
        goods: []
    },
    methods: {
        executeCommand: function (command, page) {
            var request = new XMLHttpRequest();
            request.open("GET", this.apiUrl+"?command="+command+"&page="+page, true);
            request.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    var response = JSON.parse(this.responseText);
                    if (response.errorMsg) {
                        alert(response.errorMsg);
                    } else {
                        app.goods = response.goods;
                        app.currentPage = response.page;
                        app.pagesCount = response.pagesCount;
                    }
                }
            };
            request.send();
        },
        loadGoods: function (page) {
            this.executeCommand('getitems', page);
        },
        addGood: function () {
            this.executeCommand('add', this.currentPage);
        },
        removeGood: function () {
            this.executeCommand('remove', this.currentPage);
        }
    },
    mounted: function () {
        this.loadGoods(this.currentPage);
    }
})