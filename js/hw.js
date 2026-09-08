class HandwritingCanvas {
    constructor(canvasElement, lineWidth = 3) {
        this.canvas = canvasElement;
        this.ctx = this.canvas.getContext("2d");
        this.lineWidth = lineWidth;
        
        // ตั้งค่าหัวปากกา
        this.ctx.lineCap = "round";
        this.ctx.lineJoin = "round";
        this.ctx.strokeStyle = "#000000"; // สีดำ

        this.drawing = false;
        this.history = []; // เก็บสถานะภาพเพื่อทำ Undo

        // Event Listeners สำหรับเมาส์
        this.canvas.addEventListener("mousedown", (e) => this.startDrawing(e));
        this.canvas.addEventListener("mousemove", (e) => this.draw(e));
        this.canvas.addEventListener("mouseup", () => this.stopDrawing());
        this.canvas.addEventListener("mouseout", () => this.stopDrawing());

        // Event Listeners สำหรับจอสัมผัส (Touch)
        this.canvas.addEventListener("touchstart", (e) => this.startDrawing(e.touches[0]));
        this.canvas.addEventListener("touchmove", (e) => {
            e.preventDefault();
            this.draw(e.touches[0]);
        }, { passive: false });
        this.canvas.addEventListener("touchend", () => this.stopDrawing());
    }

    getMousePos(e) {
        const rect = this.canvas.getBoundingClientRect();
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    startDrawing(e) {
        this.drawing = true;
        this.saveHistory(); // เก็บสถานะก่อนเริ่มวาดเส้นใหม่
        const pos = this.getMousePos(e);
        this.ctx.beginPath();
        this.ctx.lineWidth = this.lineWidth;
        this.ctx.moveTo(pos.x, pos.y);
    }

    draw(e) {
        if (!this.drawing) return;
        const pos = this.getMousePos(e);
        this.ctx.lineTo(pos.x, pos.y);
        this.ctx.stroke();
    }

    stopDrawing() {
        if (this.drawing) {
            this.ctx.closePath();
            this.drawing = false;
        }
    }

    setLineWidth(size) {
        this.lineWidth = size;
    }

    erase() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        this.history = [];
    }

    saveHistory() {
        // เก็บภาพปัจจุบันไว้ใน History (จำกัดไว้ 20 ขั้นตอนเพื่อไม่ให้เปลือง RAM)
        if (this.history.length > 20) this.history.shift();
        this.history.push(this.canvas.toDataURL());
    }

    undo() {
        if (this.history.length > 0) {
            const lastImage = new Image();
            lastImage.src = this.history.pop();
            lastImage.onload = () => {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.ctx.drawImage(lastImage, 0, 0);
            };
        }
    }
}