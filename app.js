var app = new Vue({
    el: '#app',
    data: {
        apiUrl: window.location.protocol+"//"+window.location.host+"/storage.php", // адрес API
        currentPage: 1,
        pagesCount: 1,
        loading: true,
        goods: [],
        dev: false
    },
    methods: {
        executeCacheCommand: function (command) {
            this.loading = true;
            var request = new XMLHttpRequest();
            request.open("GET", this.apiUrl+"?command="+command);
            request.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    app.loadGoods(1);
                }
            };
            request.send();
        },
        executeGoodsCommand: function (command, page) {
            var request = new XMLHttpRequest();
            request.open("GET", this.apiUrl+"?command="+command+"&page="+page);
            request.onreadystatechange = function () {
                if (this.readyState === 4 && this.status === 200) {
                    var response = JSON.parse(this.responseText);
                    if (response.errorMsg) {
                        alert(response.errorMsg);
                    } else {
                        app.goods = response.goods;
                        app.currentPage = response.page;
                        app.pagesCount = response.pagesCount;
                        if (app.goods.length > 0) {
                            app.loading = false;
                        }
                    }
                }
            };
            this.loading = true;
            request.send();
        },
        loadGoods: function (page) {
            this.executeGoodsCommand('getitems', page);
        },
        addGood: function () {
            this.executeGoodsCommand('add', this.currentPage);
        },
        removeGood: function () {
            this.executeGoodsCommand('remove', this.currentPage);
        },
        init: function () {
            this.executeCacheCommand('init');
        },
        flush: function () {
            this.executeCacheCommand('flush')
        }
    },
    mounted: function () {
        this.loadGoods(this.currentPage);
    }
})