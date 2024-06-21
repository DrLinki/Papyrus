<?php
$color = "";
$color.= str_pad(dechex(rand(0, 255)), 2, "0", STR_PAD_LEFT);
$color.= str_pad(dechex(rand(0, 255)), 2, "0", STR_PAD_LEFT);
$color.= str_pad(dechex(rand(0, 255)), 2, "0", STR_PAD_LEFT);
?>
<style>
    .default-page-main{
        color:#fff;
        background-color:#<?php echo $color; ?>;
        padding:50px 50px;
        margin:auto;
        text-align:center;
        text-shadow:
            0px 0px 1px #000,
            0px 0px 1px #000,
            0px 0px 1px #000,
            0px 0px 1px #000,
            0px 0px 1px #000,
            0px 0px 1px #000,
            0px 0px 1px #000,
            1px 1px 1px #000,
            2px 2px 1px #000;
    }
    h1, h2{
        font-family: Geneva, Tahoma, sans-serif;
        font-family:'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif;
    }
    .default-page-links{
        background-color:#fff;
        color:#ddd;
    }
    .default-page-link{
        display:inline-block;

        float:left;
        padding:20px;
        width:calc((100% / 3) - 40px);
        max-width:300px;
        height:200px;
        text-align:center;
        margin:auto;
    }
</style>


<div class="default-page-main">
    <h1>Papyrus</h1>
    <h2>version v0.0.1</h2>
    <p>This is the default page of your application.</p>
</div>

<div class="default-page-links">
    <a class="default-page-link" target="_blank" href="https://papyrul.org/documentation">Documentation</a>
    <a class="default-page-link" target="_blank" href="https://papyrul.org/create-your-first-page">Create your first page</a>
    <a class="default-page-link" target="_blank" href="https://papyrul.org/community">Community</a>
</div>
