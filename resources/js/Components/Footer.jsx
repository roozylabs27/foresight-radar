import React from "react";
import { Layout } from "antd";

export default function Footer() {
    const { Footer } = Layout;

    return (
        <>
            <Footer
                style={{
                    textAlign: "center",
                }}
            >
                Foresight Radar ©{new Date().getFullYear()} All Right Reserved.
            </Footer>
        </>
    );
}
