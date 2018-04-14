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
        loadGoods: function (page) {
            var request = new XMLHttpRequest();
            request.open("GET", this.apiUrl+"?command=getitems&page="+page, true);
            request.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    var response = JSON.parse(this.responseText);
                    app.goods = response.goods;
                    app.navHidden = !(app.goods.length > 0);
                    app.currentPage = response.page;
                }
            };
            request.send();
        },
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
        }
    },
    mounted: function () {
        this.getPagesCount();
        this.loadGoods(this.currentPage);
    }
})