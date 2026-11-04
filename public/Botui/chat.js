var botui = new BotUI('botui-app');
botui.message.add({content:'这里是 OurDays，我们的日常。'}).then(function(){return botui.message.add({content:'把普通的一天，慢慢写成两个人的故事。'});});
