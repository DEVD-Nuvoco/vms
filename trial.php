<div class="bulb-hover">
   <div class="bulb-area1"></div>
   <div class="bulb-area2"></div>      
        
   <div class="bulb-neck1"></div>
   <div class="bulb-neck2"></div>
</div>bulb-area1
<style>

h1{
  color: #fff;
  text-align: center;
  padding: 10px 0;
}
.bulb-area1 {
  width: 280px;
  height: 280px;
  margin: 0 auto;
  border-radius: 50%;
  background: #807d7d;
  transition:0.6s;  
}
.bulb-area2 {
  margin: -12px auto 0 auto;
  width: 142px; 
  height: 0; 
  border-left: 16px solid transparent;
  border-right: 16px solid transparent;
  border-top: 40px solid #807d7d;
  position: relative;
  transition:0.6s;
}
.bulb-area2:before{
  content: "";
  margin: -93px auto 0 auto;
  width: 172px; 
  height: 0; 
  border-left: 32px solid transparent;
  border-right: 32px solid transparent;
  border-top: 54px solid #807d7d;
  position: absolute;
  left: -47px;
  transition:0.6s;
}
.bulb-area2:after{
  content: "";
  width: 144px;
  height: 55px;
  margin: -4px auto 0 auto;
  padding: 0;
  border-radius: 0 0 900px 900px;
  background: #728b4b; 
  position: absolute;
  top: 0px;
  left: -1px;
}
.bulb-neck1 {
  background: #807d7d;
  width: 120px;
  height: 12px;   
  margin: 32px auto 0;
  padding: 14px 0;
  position: relative;
}
.bulb-neck1:before, .bulb-neck1:after{
  content: "";
  background: #694b8b;
  width: 120px;
  height: 16px;
  padding: 0;
  position: absolute;
  left: 0;
  top: 0px;
}
 .bulb-neck1:after{
  top: auto;
  bottom: 0px;
 }
.bulb-neck2 {
  margin: -1px auto 0px;
  padding: 0;
  width: 75px; 
  height: 0; 
  border-left: 25px solid transparent;
  border-right: 25px solid transparent;
  border-top: 25px solid #728b4b;  
  border-radius: 8px; 
  position: relative;
}
.bulb-neck2:after{
  content: "";
  margin: 0 auto;
  padding: 0;
  width: 25px; 
  height: 0; 
  border-left: 36px solid transparent;
  border-right: 36px solid transparent;
  border-top: 20px solid #333;
  position: relative;
  top: 20px;
  left: 0;
}
.bulb-hover:hover .bulb-area1{
  background: #eaec68;
  box-shadow: 0 0 55px 25px rgba(255,255,0,0.4);
}
.bulb-hover:hover .bulb-area2, .bulb-hover:hover .bulb-area2:before{
  border-top-color: #eaec68;
}
</style>