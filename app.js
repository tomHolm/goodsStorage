var app = new Vue({
    el: '#app',
    data: {
        apiUrl: "http://localhost/storage.php",
        emptyMessage: "Loading...",
        currentPage: 1,
        pagesCount: 1,
        goods: [],
        navHidden: true
    },
    methods: {
        getPagesCount: function () {
            var request = new XMLHttpRequest();
            request.open("GET", this.apiUrl+"?command=getpagescount", true);
            request.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    var response = JSON.parse(this.responseText);
                    app.pagesCount = response.pagesCount;
                }
            };
            request.send();
        },
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
                        app.navHidden = !(app.goods.length > 0);
                        app.currentPage = response.page;
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
        this.getPagesCount();
        this.loadGoods(this.currentPage);
    }
})