import React from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import DeleteUserForm from "./Partials/DeleteUserForm";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm";
import { Head } from "@inertiajs/react";
import {
    Layout,
    Card,
    Row,
    Col,
    Avatar,
    Typography,
    Tag,
    Space,
    Breadcrumb,
} from "antd";
import {
    UserOutlined,
    MailOutlined,
    SafetyCertificateOutlined,
    HomeOutlined,
} from "@ant-design/icons";

const { Content } = Layout;
const { Title, Text } = Typography;

export default function Edit({ auth, mustVerifyEmail, status }) {
    const user = auth?.user;
    const roleName = auth?.role || "Staff";

    return (
        <AuthenticatedLayout
            auth={auth}
            header={
                <Title level={4} style={{ margin: 0, color: "#1f1f1f" }}>
                    Pengaturan Profil
                </Title>
            }
        >
            <Head title="Profil Pengguna" />

            <Content
                style={{
                    padding: "24px 24px",
                    maxWidth: 1200,
                    width: "100%",
                    margin: "0 auto",
                }}
            >
                <Breadcrumb
                    style={{ marginBottom: 20 }}
                    items={[
                        {
                            href: route("dashboard."),
                            title: (
                                <>
                                    <HomeOutlined />
                                    <span>Dashboard</span>
                                </>
                            ),
                        },
                        {
                            title: "Profil Pengguna",
                        },
                    ]}
                />

                <Row gutter={[24, 24]}>
                    <Col xs={24} md={8}>
                        <Card
                            bordered={false}
                            style={{
                                borderRadius: 12,
                                boxShadow: "0 2px 8px rgba(0,0,0,0.04)",
                                textAlign: "center",
                                position: "sticky",
                                top: 88,
                            }}
                        >
                            <div style={{ padding: "16px 0" }}>
                                <Avatar
                                    size={80}
                                    style={{
                                        backgroundColor: "#1677ff",
                                        fontSize: 32,
                                        fontWeight: 600,
                                        boxShadow:
                                            "0 4px 12px rgba(22, 119, 255, 0.3)",
                                        marginBottom: 16,
                                    }}
                                >
                                    {user?.name
                                        ? user.name.charAt(0).toUpperCase()
                                        : "U"}
                                </Avatar>

                                <Title level={4} style={{ margin: 0 }}>
                                    {user?.name}
                                </Title>
                                <Space
                                    align="center"
                                    style={{
                                        marginTop: 4,
                                        marginBottom: 12,
                                        color: "#8c8c8c",
                                    }}
                                >
                                    <MailOutlined style={{ fontSize: 13 }} />
                                    <Text type="secondary" style={{ fontSize: 13 }}>
                                        {user?.email}
                                    </Text>
                                </Space>

                                <div>
                                    <Tag
                                        icon={<SafetyCertificateOutlined />}
                                        color="blue"
                                        style={{
                                            padding: "2px 10px",
                                            borderRadius: 12,
                                            fontWeight: 500,
                                        }}
                                    >
                                        Peran: {roleName}
                                    </Tag>
                                </div>
                            </div>
                        </Card>
                    </Col>

                    <Col xs={24} md={16}>
                        <Space
                            direction="vertical"
                            size="large"
                            style={{ width: "100%" }}
                        >
                            <Card
                                bordered={false}
                                style={{
                                    borderRadius: 12,
                                    boxShadow: "0 2px 8px rgba(0,0,0,0.04)",
                                }}
                            >
                                <UpdateProfileInformationForm
                                    mustVerifyEmail={mustVerifyEmail}
                                    status={status}
                                />
                            </Card>

                            <Card
                                bordered={false}
                                style={{
                                    borderRadius: 12,
                                    boxShadow: "0 2px 8px rgba(0,0,0,0.04)",
                                }}
                            >
                                <UpdatePasswordForm />
                            </Card>

                            <Card
                                bordered={false}
                                style={{
                                    borderRadius: 12,
                                    boxShadow: "0 2px 8px rgba(0,0,0,0.04)",
                                    border: "1px solid #ffccc7",
                                }}
                            >
                                <DeleteUserForm />
                            </Card>
                        </Space>
                    </Col>
                </Row>
            </Content>
        </AuthenticatedLayout>
    );
}
