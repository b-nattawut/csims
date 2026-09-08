(function(window, document) {

    // Establish the root object, `window` (`self`) in the browser, 
    // or `this` in some virtual machines. We use `self`
    // instead of `window` for `WebWorker` support.
    var root = typeof self === 'object' && self.self === self && self || this;

    // Create a safe reference to the handwriting object for use below.        
    var handwriting = function(obj) {
        if (obj instanceof handwriting) return obj;
        if (!(this instanceof handwriting)) return new handwriting(obj);
        this._wrapped = obj;
    };

    root.handwriting = handwriting;

    handwriting.Canvas = function(cvs, lineWidth) {
        this.canvas = cvs;
        this.cxt = cvs.getContext("2d");
        this.cxt.lineCap = "round";
        this.cxt.lineJoin = "round";
        this.lineWidth = lineWidth || 2;
        this.width = cvs.width;
        this.height = cvs.height;
        this.drawing = false;
        this.handwritingX = [];
        this.handwritingY = [];
        this.trace = [];
        this.options = {};
        this.step = [];
        this.redo_step = [];
        this.redo_trace = [];
        this.allowUndo = false;
        this.allowRedo = false;
        cvs.addEventListener("mousedown", this.mouseDown.bind(this));
        cvs.addEventListener("mousemove", this.mouseMove.bind(this));
        cvs.addEventListener("mouseup", this.mouseUp.bind(this));
        cvs.addEventListener("mouseout", this.mouseOut.bind(this));
        cvs.addEventListener("touchstart", this.touchStart.bind(this), {passive: false});
        cvs.addEventListener("touchmove", this.touchMove.bind(this), {passive: false});
        cvs.addEventListener("touchend", this.touchEnd.bind(this));
        this.callback = undefined;
        this.recognize = handwriting.recognize;
    };
    /**
     * [toggle_Undo_Redo description]
     * @return {[type]} [description]
     */
    handwriting.Canvas.prototype.set_Undo_Redo = function(undo, redo) {
        this.allowUndo = undo;
        this.allowRedo = undo ? redo : false;
        if (!this.allowUndo) {
            this.step = [];
            this.redo_step = [];
            this.redo_trace = [];
        }
    };

    handwriting.Canvas.prototype.setLineWidth = function(lineWidth) {
        this.lineWidth = lineWidth;
    };

    handwriting.Canvas.prototype.setCallBack = function(callback) {
        this.callback = callback;
    };

    handwriting.Canvas.prototype.setOptions = function(options) {
        this.options = options;
    };


    handwriting.Canvas.prototype.mouseDown = function(e) {
        // new stroke - clear redo history when starting fresh stroke after undo
        if (this.allowRedo && this.redo_step.length > 0) {
            this.redo_step = [];
            this.redo_trace = [];
        }
        this.cxt.lineWidth = this.lineWidth;
        this.handwritingX = [];
        this.handwritingY = [];
        this.drawing = true;
        this.cxt.beginPath();
        var rect = this.canvas.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;
        this.cxt.moveTo(x, y);
        this.handwritingX.push(x);
        this.handwritingY.push(y);
    };


    handwriting.Canvas.prototype.mouseMove = function(e) {
        if (this.drawing) {
            var rect = this.canvas.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            this.cxt.lineTo(x, y);
            this.cxt.stroke();
            this.handwritingX.push(x);
            this.handwritingY.push(y);
        }
    };

    handwriting.Canvas.prototype.mouseUp = function(e) {
        this.endStroke();
    };

    handwriting.Canvas.prototype.mouseOut = function(e) {
        if (this.drawing) {
            this.endStroke();
        }
    };

    handwriting.Canvas.prototype.endStroke = function() {
        if (!this.drawing) return;
        var w = [];
        w.push(this.handwritingX);
        w.push(this.handwritingY);
        w.push([]);
        this.trace.push(w);
        this.drawing = false;
        if (this.allowUndo) this.step.push(this.canvas.toDataURL());
    };


    handwriting.Canvas.prototype.touchStart = function(e) {
        e.preventDefault();
        // Clear redo history when starting fresh stroke after undo
        if (this.allowRedo && this.redo_step.length > 0) {
            this.redo_step = [];
            this.redo_trace = [];
        }
        this.drawing = true;
        this.cxt.lineWidth = this.lineWidth;
        this.handwritingX = [];
        this.handwritingY = [];
        var de = document.documentElement;
        var box = this.canvas.getBoundingClientRect();
        var top = box.top + window.pageYOffset - de.clientTop;
        var left = box.left + window.pageXOffset - de.clientLeft;
        var touch = e.changedTouches[0];
        var touchX = touch.pageX - left;
        var touchY = touch.pageY - top;
        this.handwritingX.push(touchX);
        this.handwritingY.push(touchY);
        this.cxt.beginPath();
        this.cxt.moveTo(touchX, touchY);
    };

    handwriting.Canvas.prototype.touchMove = function(e) {
        e.preventDefault();
        if (!this.drawing) return;
        var touch = e.targetTouches[0];
        if (!touch) return;
        var de = document.documentElement;
        var box = this.canvas.getBoundingClientRect();
        var top = box.top + window.pageYOffset - de.clientTop;
        var left = box.left + window.pageXOffset - de.clientLeft;
        var x = touch.pageX - left;
        var y = touch.pageY - top;
        this.handwritingX.push(x);
        this.handwritingY.push(y);
        this.cxt.lineTo(x, y);
        this.cxt.stroke();
    };

    handwriting.Canvas.prototype.touchEnd = function(e) {
        this.endStroke();
    };

    handwriting.Canvas.prototype.undo = function() {
        if (!this.allowUndo || this.step.length <= 0) return;
        else if (this.step.length === 1) {
            if (this.allowRedo) {
                this.redo_step.push(this.step.pop());
                this.redo_trace.push(this.trace.pop());
                this.cxt.clearRect(0, 0, this.width, this.height);
            }
        } else {
            if (this.allowRedo) {
                this.redo_step.push(this.step.pop());
                this.redo_trace.push(this.trace.pop());
            } else {
                this.step.pop();
                this.trace.pop();
            }
            loadFromUrl(this.step.slice(-1)[0], this);
        }
    };

    handwriting.Canvas.prototype.redo = function() {
        if (!this.allowRedo || this.redo_step.length <= 0) return;
        this.step.push(this.redo_step.pop());
        this.trace.push(this.redo_trace.pop());
        loadFromUrl(this.step.slice(-1)[0], this);
    };

    handwriting.Canvas.prototype.erase = function() {
        this.cxt.clearRect(0, 0, this.width, this.height);
        this.step = [];
        this.redo_step = [];
        this.redo_trace = [];
        this.trace = [];
    };

    function loadFromUrl(url, cvs) {
        var imageObj = new Image();
        imageObj.onload = function() {
            cvs.cxt.clearRect(0, 0, cvs.width, cvs.height);
            cvs.cxt.drawImage(imageObj, 0, 0);
        };
        imageObj.onerror = function() {
            // Silently fail - canvas stays blank if image fails to load
        };
        imageObj.src = url;
    }

    /**
     * Recognize handwriting on the canvas.
     * @param {array} trace - Handwriting trace data.
     * @param {object} options - Options for recognition.
     * @param {function} callback - Callback function for recognition results.
     */
    handwriting.recognize = function(trace, options, callback) {
        if (handwriting.Canvas && this instanceof handwriting.Canvas) {
            trace = this.trace;
            options = this.options;
            callback = this.callback;
        } else if (!options) options = {};
        // Get canvas dimensions safely (for static call, this.width may be undefined)
        var canvasWidth = options.width || (this instanceof handwriting.Canvas ? this.width : undefined) || 660;
        var canvasHeight = options.height || (this instanceof handwriting.Canvas ? this.height : undefined) || 250;
        var data = JSON.stringify({
            "options": "enable_pre_space",
            "requests": [{
                "writing_guide": {
                    "writing_area_width": canvasWidth,
                    "writing_area_height": canvasHeight
                },
                "ink": trace,
                "language": options.language || "th"
            }]
        });
        var xhr = new XMLHttpRequest();
        xhr.timeout = 10000; // 10 seconds timeout
        xhr.addEventListener("readystatechange", function() {
            if (this.readyState === 4) {
                switch (this.status) {
                    case 200:
                        try {
                            var response = JSON.parse(this.responseText);
                            var results;
                            if (response.length === 1) {
                                callback(undefined, new Error(response[0]));
                            } else if (response[1] && response[1][0] && response[1][0][1]) {
                                results = response[1][0][1];
                                if (!!options.numOfWords) {
                                    results = results.filter(function(result) {
                                        return (result.length == options.numOfWords);
                                    });
                                }
                                if (!!options.numOfReturn) {
                                    results = results.slice(0, options.numOfReturn);
                                }
                                callback(results, undefined);
                            } else {
                                callback(undefined, new Error("invalid response format"));
                            }
                        } catch (e) {
                            callback(undefined, new Error("parse error: " + e.message));
                        }
                        break;
                    case 403:
                        callback(undefined, new Error("access denied"));
                        break;
                    case 503:
                        callback(undefined, new Error("can't connect to recognition server"));
                        break;
                    case 0:
                        callback(undefined, new Error("network error or timeout"));
                        break;
                    default:
                        callback(undefined, new Error("server error: " + this.status));
                }
            }
        });
        xhr.addEventListener("timeout", function() {
            callback(undefined, new Error("request timeout"));
        });
        xhr.addEventListener("error", function() {
            callback(undefined, new Error("network error"));
        });
        xhr.open("POST", "https://www.google.com.tw/inputtools/request?ime=handwriting&app=mobilesearch&cs=1&oe=UTF-8");
        xhr.setRequestHeader("content-type", "application/json");
        xhr.send(data);
    };

})(window, document);