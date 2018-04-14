var app = new Vue({
    el: '#app',
    data: {
        apiUrl: "http://localhost/storage.php",
        emptyMessage: "Loading...",
        page: 1,
        pagesCount: 1,
        goods: []
    },
    methods: {
        loadGoods: function () {
            request = new XMLHttpRequest();
            request.open("GET", this.apiUrl+"?command=getitems&page="+this.page, true);

            request.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    var response = JSON.parse(this.responseText);
                    app.goods = response.goods;
                    app.emptyMessage = app.goods.length > 0 ? "Loading..." : "Nothing to display";
                    app.pagesCount = response.pagesCount;
                    if (app.pagesCount < app.page) {
                        app.page = app.pagesCount;
                    }
                    document.getElementById("prvBtn").disabled = app.page <= 1;
                    document.getElementById("nxtBtn").disabled = app.page >= app.pagesCount;
                }
            }

            request.send();
        },
        nextPage: function () {
            this.page++;
            this.loadGoods();
        },
        prevPage: function () {
            this.page--;
            this.loadGoods();
        }
    },
    mounted: function () {
        this.loadGoods();
    }
})